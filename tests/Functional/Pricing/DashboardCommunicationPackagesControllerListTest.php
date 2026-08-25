<?php

namespace App\Tests\Functional\Pricing;

use App\Controller\DashboardCommunicationPackagesController;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPackageBindingService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\DashboardCommunicationPackagesController
 *
 * Contra Postgres real — a propósito: el filtro por tag depende de una
 * comparación LIKE contra la representación de texto de una columna `json`
 * (no `jsonb`), que Doctrine DQL no puede expresar directamente (Postgres
 * rechaza `json ~~ unknown`) — solo un test contra SQL real confirma que el
 * cast manual (`tags::text LIKE ...`) funciona de verdad.
 */
class DashboardCommunicationPackagesControllerListTest extends ProviderFunctionalTestCase
{
    private static int $counter = 0;

    private function controller(): DashboardCommunicationPackagesController
    {
        $controller = new DashboardCommunicationPackagesController(
            $this->em,
            self::getContainer()->get(CommunicationPackageAdminService::class),
            self::getContainer()->get(PackageCatalogResolver::class),
            self::getContainer()->get(CommunicationPackageBindingService::class),
            self::getContainer()->get(CommunicationPackageProviderProductRepository::class),
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        return $controller;
    }

    private function communicationPackage(string $name, array $tags = []): CommunicationPackage
    {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName($name)
            ->setDescription($name)
            ->setDestinationAmount(500.0 + self::$counter)
            ->setDestinationCurrency('CUP')
            ->setTags($tags);

        $this->em->persist($package);

        return $package;
    }

    /**
     * createdAt solo se fija vía #[ORM\PrePersist] (siempre "ahora") — para
     * un test de orden determinístico hace falta pisarlo con SQL crudo
     * después del flush, no hay setter público a propósito (ver entidad).
     */
    private function setCreatedAt(CommunicationPackage $package, \DateTimeImmutable $when): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE communication_package SET created_at = :when WHERE id = :id',
            ['when' => $when->format('Y-m-d H:i:s'), 'id' => $package->getId()]
        );
    }

    public function testFiltersByTagUsingRawJsonTextComparison(): void
    {
        $internet = $this->communicationPackage('Nauta WiFi', ['INTERNET']);
        $airtime = $this->communicationPackage('Recarga simple', ['AIRTIME']);
        $this->em->flush();
        $this->em->clear();

        $response = $this->controller()->list(new Request(['tag' => 'INTERNET']));
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['results']);
        $this->assertSame($internet->getId(), $data['results'][0]['id']);
    }

    public function testFilterByTagNeverMatchesASubstringOfAnotherTag(): void
    {
        // UNLIMITED contiene "LIMIT" como substring — el LIKE debe matchear
        // por el valor JSON completo entre comillas ("TAG"), no cualquier
        // substring suelto del array serializado.
        $this->communicationPackage('Nauta PLUS', ['INTERNET', 'UNLIMITED']);
        $this->em->flush();
        $this->em->clear();

        $response = $this->controller()->list(new Request(['tag' => 'LIMIT']));
        $data = json_decode($response->getContent(), true);

        $this->assertCount(0, $data['results']);
    }

    public function testFiltersByInPromotionTrueAndFalse(): void
    {
        $promotion = (new CommunicationPromotions())
            ->setName('Promo test')
            ->setDescription('Promo test')
            ->setStartAt(new \DateTimeImmutable('-1 day'))
            ->setEndAt(new \DateTimeImmutable('+5 days'));
        $this->em->persist($promotion);

        $regular = $this->communicationPackage('Catálogo normal');
        $promoPackage = $this->communicationPackage('Paquete de promo');
        $promoPackage->setPromotion($promotion);
        $this->em->flush();
        $this->em->clear();

        $onlyPromo = json_decode($this->controller()->list(new Request(['inPromotion' => 'true']))->getContent(), true);
        $this->assertCount(1, $onlyPromo['results']);
        $this->assertSame($promoPackage->getId(), $onlyPromo['results'][0]['id']);
        $this->assertSame($promotion->getId(), $onlyPromo['results'][0]['promotionId']);

        $onlyRegular = json_decode($this->controller()->list(new Request(['inPromotion' => 'false']))->getContent(), true);
        $this->assertCount(1, $onlyRegular['results']);
        $this->assertSame($regular->getId(), $onlyRegular['results'][0]['id']);
        $this->assertNull($onlyRegular['results'][0]['promotionId']);
    }

    public function testFiltersByCreatedAtDateRange(): void
    {
        $old = $this->communicationPackage('Paquete viejo');
        $recent = $this->communicationPackage('Paquete reciente');
        $this->em->flush();
        $this->setCreatedAt($old, new \DateTimeImmutable('2026-01-01 10:00:00'));
        $this->setCreatedAt($recent, new \DateTimeImmutable('2026-06-15 10:00:00'));
        $this->em->clear();

        $data = json_decode(
            $this->controller()->list(new Request(['createdFrom' => '2026-06-01', 'createdTo' => '2026-06-30']))->getContent(),
            true
        );

        $this->assertCount(1, $data['results']);
        $this->assertSame($recent->getId(), $data['results'][0]['id']);
    }

    public function testSortsByCreatedAtAscendingAndDescending(): void
    {
        $first = $this->communicationPackage('Primero creado');
        $second = $this->communicationPackage('Segundo creado');
        $this->em->flush();
        $this->setCreatedAt($first, new \DateTimeImmutable('2026-01-01 10:00:00'));
        $this->setCreatedAt($second, new \DateTimeImmutable('2026-02-01 10:00:00'));
        $this->em->clear();

        $asc = json_decode($this->controller()->list(new Request(['orderBy' => 'createdAt ASC']))->getContent(), true);
        $this->assertSame([$first->getId(), $second->getId()], array_column($asc['results'], 'id'));

        $desc = json_decode($this->controller()->list(new Request(['orderBy' => 'createdAt DESC']))->getContent(), true);
        $this->assertSame([$second->getId(), $first->getId()], array_column($desc['results'], 'id'));
    }
}
