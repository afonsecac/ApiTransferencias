<?php

namespace App\Tests\Functional\Provider;

use App\Controller\AdminProductsController;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\AdminProductsController::products
 *
 * El scope admin de /apps/products usaba parámetros distintos de los que
 * manda el dashboard (query/env en vez de search/environmentType), ignoraba
 * provider y orderBy, y devolvía otro sobre de paginación (page/total en
 * vez de currentPage/hasNext/hasPrevious) — la paginación y los filtros no
 * funcionaban. Contra Postgres real; cada test aísla sus productos con un
 * texto único en la descripción.
 */
class AdminProductsControllerFilterSortTest extends ProviderFunctionalTestCase
{
    private function controller(): AdminProductsController
    {
        return self::getContainer()->get(AdminProductsController::class);
    }

    private function product(string $tag, string $provider, int $packageId, float $price, ?Environment $environment = null): CommunicationProduct
    {
        $environment ??= $this->createEnvironment('TEST');

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId($packageId)
            ->setPackageType('RECHARGE')
            ->setPrice($price)
            ->setEnabled(true)
            ->setDescription("{$tag} {$provider} {$packageId}")
            ->setProvider($provider)
            ->setExternalRef((string) $packageId)
            ->setEndDateAt(null);
        $this->em->persist($product);

        return $product;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function list(array $query): array
    {
        $response = $this->controller()->products(new Request($query));

        return json_decode((string) $response->getContent(), true);
    }

    public function testReturnsTheSameEnvelopeAsTheClientScope(): void
    {
        $env = $this->createEnvironment('TEST');
        $this->product('ZZENV', 'ETECSA', 88001, 1.0, $env);
        $this->em->flush();

        $data = $this->list(['search' => 'ZZENV', 'environmentType' => 'TEST']);

        foreach (['results', 'limit', 'currentPage', 'hasNext', 'hasPrevious'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertSame(0, $data['currentPage']);
        $this->assertFalse($data['hasPrevious']);
        $this->assertSame('TEST', $data['results'][0]['environment']['type']);
        $this->assertSame('ETECSA', $data['results'][0]['provider']);
    }

    public function testPaginationFlagsFollowTheRequestedPage(): void
    {
        $env = $this->createEnvironment('TEST');
        foreach ([88101, 88102, 88103] as $packageId) {
            $this->product('ZZPAGE', 'ETECSA', $packageId, 1.0, $env);
        }
        $this->em->flush();

        $first = $this->list(['search' => 'ZZPAGE', 'limit' => 2, 'page' => 0]);
        $second = $this->list(['search' => 'ZZPAGE', 'limit' => 2, 'page' => 1]);

        $this->assertCount(2, $first['results']);
        $this->assertTrue($first['hasNext']);
        $this->assertFalse($first['hasPrevious']);
        $this->assertCount(1, $second['results']);
        $this->assertFalse($second['hasNext']);
        $this->assertTrue($second['hasPrevious']);
        $this->assertSame(1, $second['currentPage']);
    }

    public function testFiltersByProviderAndSearch(): void
    {
        $env = $this->createEnvironment('TEST');
        $this->product('ZZFILT', 'ETECSA', 88201, 1.0, $env);
        $this->product('ZZFILT', 'CSQ', 88202, 1.0, $env);
        $this->product('OTRO', 'CSQ', 88203, 1.0, $env);
        $this->em->flush();

        $byProvider = $this->list(['search' => 'ZZFILT', 'provider' => 'CSQ']);
        $this->assertCount(1, $byProvider['results']);
        $this->assertSame('CSQ', $byProvider['results'][0]['provider']);

        $bySearch = $this->list(['search' => 'ZZFILT']);
        $this->assertCount(2, $bySearch['results']);
    }

    public function testFiltersByEnvironmentTypeAndReturnsEveryEnvironmentWhenOmitted(): void
    {
        $prod = $this->createEnvironment('PROD');
        $test = $this->createEnvironment('TEST');
        $this->product('ZZTYPE', 'ETECSA', 88301, 1.0, $prod);
        $this->product('ZZTYPE', 'ETECSA', 88302, 1.0, $test);
        $this->em->flush();

        $onlyProd = $this->list(['search' => 'ZZTYPE', 'environmentType' => 'PROD']);
        $this->assertCount(1, $onlyProd['results']);
        $this->assertSame('PROD', $onlyProd['results'][0]['environment']['type']);

        $all = $this->list(['search' => 'ZZTYPE']);
        $this->assertCount(2, $all['results']);
    }

    public function testOrdersByTheRequestedFieldAndDirection(): void
    {
        $env = $this->createEnvironment('TEST');
        $this->product('ZZSORT', 'ETECSA', 88401, 30.0, $env);
        $this->product('ZZSORT', 'ETECSA', 88402, 10.0, $env);
        $this->product('ZZSORT', 'ETECSA', 88403, 20.0, $env);
        $this->em->flush();

        $asc = $this->list(['search' => 'ZZSORT', 'orderBy' => 'price ASC']);
        $desc = $this->list(['search' => 'ZZSORT', 'orderBy' => 'price DESC']);

        $this->assertSame([10.0, 20.0, 30.0], array_map('floatval', array_column($asc['results'], 'price')));
        $this->assertSame([30.0, 20.0, 10.0], array_map('floatval', array_column($desc['results'], 'price')));
    }

    public function testIgnoresAnUnknownSortFieldInsteadOfFailing(): void
    {
        $env = $this->createEnvironment('TEST');
        $this->product('ZZBAD', 'ETECSA', 88501, 1.0, $env);
        $this->em->flush();

        $data = $this->list(['search' => 'ZZBAD', 'orderBy' => 'password; DROP TABLE x DESC']);

        $this->assertCount(1, $data['results']);
    }
}
