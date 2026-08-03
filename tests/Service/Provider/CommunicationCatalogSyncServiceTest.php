<?php

namespace App\Tests\Service\Provider;

use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderCatalogInterface;
use App\Provider\Contract\ProviderProductDto;
use App\Provider\ProviderRegistry;
use App\Service\Provider\CommunicationCatalogSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\CommunicationCatalogSyncService
 */
class CommunicationCatalogSyncServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private EntityRepository&MockObject $productRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->productRepo = $this->createMock(EntityRepository::class);
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $this->productRepo],
        ]);
    }

    /**
     * @param ProviderCatalogInterface&CommunicationProviderInterface $adapter
     */
    private function serviceWithAdapter(object $adapter): CommunicationCatalogSyncService
    {
        $registry = new ProviderRegistry([$adapter]);

        return new CommunicationCatalogSyncService($this->em, $registry);
    }

    private function fakeProduct(string $externalId, string $name = 'Combo', float $price = 10.0, bool $enabled = true): ProviderProductDto
    {
        return new ProviderProductDto(
            externalId: $externalId,
            name: $name,
            description: null,
            productTypeRaw: 'A',
            wholesalePrice: $price,
            priceCurrency: 'USD',
            destinationAmount: null,
            destinationMinAmount: null,
            destinationMaxAmount: null,
            destinationUnit: null,
            benefits: [],
            enabled: $enabled,
            validFrom: null,
            validTo: null,
            raw: [],
            isMobileOrInternetService: true,
            service: ['name' => 'MOBILE', 'subservice' => ['name' => 'AIRTIME']],
        );
    }

    private function etecsaAdapterReturning(array $products): ProviderCatalogInterface&MockObject
    {
        $adapter = $this->createMock(ProviderCatalogInterface::class);
        $adapter->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $adapter->method('fetchProducts')->willReturn($products);

        return $adapter;
    }

    public function testCreatesNewProductWhenNotFound(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $this->productRepo->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationProduct::class));
        $this->em->expects($this->once())->method('flush');

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('100', 'Combo 100', 12.5, true)]);
        $service = $this->serviceWithAdapter($adapter);

        $result = $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
    }

    public function testUpdatesExistingProductWithoutPersisting(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $existing = new CommunicationProduct();
        $this->productRepo->method('findOneBy')->willReturn($existing);
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('100', 'Combo actualizado', 20.0, false)]);
        $service = $this->serviceWithAdapter($adapter);

        $result = $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame('Combo actualizado', $existing->getDescription());
        $this->assertSame(20.0, $existing->getPrice());
        $this->assertFalse($existing->isEnabled());
    }

    public function testUpsertKeyIncludesProviderAndExternalRef(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $this->productRepo->expects($this->once())
            ->method('findOneBy')
            ->with([
                'environment' => $environment,
                'provider' => 'ETECSA',
                'externalRef' => '100',
            ])
            ->willReturn(null);

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('100')]);
        $service = $this->serviceWithAdapter($adapter);

        $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);
    }

    public function testSkipsProductsWithEmptyExternalId(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $this->em->expects($this->never())->method('persist');

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('')]);
        $service = $this->serviceWithAdapter($adapter);

        $result = $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(1, $result->skipped);
    }

    public function testSetsNewProductFieldsFromDto(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $this->productRepo->method('findOneBy')->willReturn(null);

        /** @var CommunicationProduct|null $persisted */
        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted = $entity;
        });

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('100', 'Combo 100', 12.5, true)]);
        $service = $this->serviceWithAdapter($adapter);

        $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);

        $this->assertNotNull($persisted);
        $this->assertSame('ETECSA', $persisted->getProvider());
        $this->assertSame('100', $persisted->getExternalRef());
        $this->assertSame(100, $persisted->getPackageId());
        $this->assertSame('Combo 100', $persisted->getDescription());
        $this->assertSame(12.5, $persisted->getPrice());
        $this->assertTrue($persisted->isEnabled());
        $this->assertSame($environment, $persisted->getEnvironment());
    }

    public function testNonNumericExternalIdFallsBackToZeroPackageId(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('PROD');
        $environment->method('getId')->willReturn(4);

        $this->productRepo->method('findOneBy')->willReturn(null);

        /** @var CommunicationProduct|null $persisted */
        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted = $entity;
        });

        $adapter = $this->etecsaAdapterReturning([$this->fakeProduct('SKU-ABC')]);
        $service = $this->serviceWithAdapter($adapter);

        $service->syncProducts(CommunicationProviderEnum::ETECSA, $environment);

        $this->assertSame(0, $persisted->getPackageId());
        $this->assertSame('SKU-ABC', $persisted->getExternalRef());
    }
}
