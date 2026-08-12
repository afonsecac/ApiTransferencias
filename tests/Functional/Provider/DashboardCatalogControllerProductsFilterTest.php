<?php

namespace App\Tests\Functional\Provider;

use App\Controller\DashboardCatalogController;
use App\Entity\CommunicationProduct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\DashboardCatalogController::products
 *
 * Filtro por `provider` (el "Operador" pedido por el usuario: ETECSA/DTOne/
 * CSQ) — el campo ya existía en CommunicationProduct pero GET /products no
 * lo exponía como filtro de query. Contra Postgres real porque construye su
 * propio QueryBuilder directamente (no hay repo method que mockear).
 */
class DashboardCatalogControllerProductsFilterTest extends ProviderFunctionalTestCase
{
    private function controller(): DashboardCatalogController
    {
        return self::getContainer()->get(DashboardCatalogController::class);
    }

    private function product(string $provider, int $packageId, ?\App\Entity\Environment $environment = null): CommunicationProduct
    {
        $environment ??= $this->createEnvironment();

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId($packageId)
            ->setPackageType('RECHARGE')
            ->setPrice(10.0)
            ->setEnabled(true)
            ->setDescription("Producto {$provider} {$packageId}")
            ->setProvider($provider)
            ->setExternalRef((string) $packageId)
            // Vigencia abierta — mismo shape que un producto real sincronizado
            // (ver DashboardCatalogController::products(), que ahora exige
            // initialDate <= now <= endDateAt).
            ->setEndDateAt(null);

        $this->em->persist($product);

        return $product;
    }

    public function testFiltersProductsByProvider(): void
    {
        $this->product('ETECSA', 9001);
        $this->product('DTONE', 9002);
        $this->product('CSQ', 9003);
        $this->em->flush();

        $request = new Request(['provider' => 'CSQ']);
        $response = $this->controller()->products($request);

        $data = json_decode($response->getContent(), true);

        $this->assertGreaterThanOrEqual(1, count($data['results']));
        foreach ($data['results'] as $result) {
            $this->assertSame('CSQ', $result['provider']);
        }
    }

    public function testWithoutProviderFilterReturnsProductsFromAnyProvider(): void
    {
        $this->product('ETECSA', 9101);
        $this->product('DTONE', 9102);
        $this->em->flush();

        $request = new Request(['limit' => 100]);
        $response = $this->controller()->products($request);

        $data = json_decode($response->getContent(), true);
        $providers = array_unique(array_column($data['results'], 'provider'));

        $this->assertGreaterThanOrEqual(2, count($providers));
    }

    public function testFiltersProductsByEnvironmentId(): void
    {
        $envA = $this->createEnvironment();
        $envB = $this->createEnvironment();
        $productA = $this->product('CSQ', 9201, $envA);
        $this->product('CSQ', 9202, $envB);
        $this->em->flush();

        $request = new Request(['environmentId' => (string) $envA->getId()]);
        $response = $this->controller()->products($request);

        $data = json_decode($response->getContent(), true);
        $ids = array_column($data['results'], 'id');

        $this->assertContains($productA->getId(), $ids);
        foreach ($data['results'] as $result) {
            $this->assertSame($envA->getId(), $result['environment']['id']);
        }
    }

    public function testExcludesProductsOutsideTheirActiveDateRange(): void
    {
        $active = $this->product('CSQ', 9301);

        $expired = $this->product('CSQ', 9302);
        $expired->setEndDateAt(new \DateTimeImmutable('-1 day'));

        $notYetStarted = $this->product('CSQ', 9303);
        $notYetStarted->setInitialDate(new \DateTimeImmutable('+1 day'));
        $this->em->flush();

        $request = new Request(['limit' => 100, 'provider' => 'CSQ']);
        $response = $this->controller()->products($request);

        $data = json_decode($response->getContent(), true);
        $ids = array_column($data['results'], 'id');

        $this->assertContains($active->getId(), $ids);
        $this->assertNotContains($expired->getId(), $ids);
        $this->assertNotContains($notYetStarted->getId(), $ids);
    }
}
