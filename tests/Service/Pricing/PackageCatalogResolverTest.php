<?php

namespace App\Tests\Service\Pricing;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationContractRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationProductRepository;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ResolvedProductPrice;
use App\Repository\SysConfigRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Service\Pricing\PackageCatalogResolver
 *
 * Precedencia probada explícitamente: contrato propio del tenant > contrato
 * por defecto (tenant NULL) > catálogo completo con MAX(price)+margen. La
 * PRESENCIA de contratos (propios o por defecto) restringe la visibilidad
 * por completo — un paquete no cubierto por el contrato NO cae a MAX, se
 * considera no visible.
 */
class PackageCatalogResolverTest extends TestCase
{
    private CommunicationContractRepository&MockObject $contractRepository;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CommunicationProductRepository&MockObject $productRepository;
    private ProductPriceResolver&MockObject $productPriceResolver;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private PackageCatalogResolver $resolver;

    protected function setUp(): void
    {
        $this->contractRepository = $this->createMock(CommunicationContractRepository::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);
        $this->productPriceResolver = $this->createMock(ProductPriceResolver::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);

        $this->resolver = new PackageCatalogResolver(
            $this->contractRepository,
            $this->packageRepository,
            $this->productRepository,
            $this->productPriceResolver,
            $this->sysConfigRepo,
            $this->createMock(LoggerInterface::class),
        );
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function account(int $id, string $currency = 'USD'): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getCurrency')->willReturn($currency);

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($this->createMock(Environment::class));

        return $account;
    }

    private function package(int $id, float $amount = 500.0, string $currency = 'CUP'): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName("Paquete {$id}")
            ->setDescription("Paquete {$id}")
            ->setDestinationAmount($amount)
            ->setDestinationCurrency($currency);
        $this->assignId($package, $id);

        return $package;
    }

    private function contract(CommunicationPackage $package, float $price, string $currency = 'USD'): CommunicationContract
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($package)
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setPrice($price)
            ->setCurrency($currency);
        $this->assignId($contract, random_int(1000, 999999));

        return $contract;
    }

    // ---- catalogFor(): precedencia ----

    public function testCatalogForUsesOnlyTenantContractsWhenPresent(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);
        $contract = $this->contract($package, 15.0);

        $this->contractRepository->method('findActiveForTenant')->with($account)->willReturn([$contract]);
        $this->contractRepository->expects($this->never())->method('findActiveDefaults');
        $this->packageRepository->expects($this->never())->method('findActiveCatalog');

        $offers = $this->resolver->catalogFor($account);

        $this->assertCount(1, $offers);
        $this->assertSame(15.0, $offers[0]->price);
        $this->assertSame(PackageOfferSourceEnum::TENANT_CONTRACT, $offers[0]->source);
        $this->assertSame($package, $offers[0]->package);
    }

    public function testCatalogForFallsBackToDefaultContractsWhenTenantHasNone(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);
        $default = $this->contract($package, 12.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([$default]);
        $this->packageRepository->expects($this->never())->method('findActiveCatalog');

        $offers = $this->resolver->catalogFor($account);

        $this->assertCount(1, $offers);
        $this->assertSame(12.0, $offers[0]->price);
        $this->assertSame(PackageOfferSourceEnum::DEFAULT_CONTRACT, $offers[0]->source);
    }

    public function testCatalogForFallsBackToProductMaxWhenNoContractsAtAll(): void
    {
        $account = $this->account(1, 'USD');
        $package = $this->package(10, 500.0, 'CUP');
        $product = $this->createMock(CommunicationProduct::class);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->packageRepository->method('findActiveCatalog')->willReturn([$package]);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(10.0, 'USD', null, null, null));

        $offers = $this->resolver->catalogFor($account);

        $this->assertCount(1, $offers);
        $this->assertSame(10.0, $offers[0]->price);
        $this->assertSame(PackageOfferSourceEnum::PRODUCT_MAX, $offers[0]->source);
    }

    public function testCatalogForPicksTheMaxAmongMultipleProviders(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);
        $cheap = $this->createMock(CommunicationProduct::class);
        $expensive = $this->createMock(CommunicationProduct::class);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->packageRepository->method('findActiveCatalog')->willReturn([$package]);
        $this->productRepository->method('findMatchingDestination')->willReturn([$cheap, $expensive]);
        $this->productPriceResolver->method('resolve')->willReturnMap([
            [$cheap, 'USD', 1, new ResolvedProductPrice(5.0, 'USD', null, null, null)],
            [$expensive, 'USD', 1, new ResolvedProductPrice(9.0, 'USD', null, null, null)],
        ]);

        $offers = $this->resolver->catalogFor($account);

        $this->assertSame(9.0, $offers[0]->price);
    }

    public function testCatalogForAppliesConfiguredMarginOnTopOfTheMax(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);
        $product = $this->createMock(CommunicationProduct::class);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->packageRepository->method('findActiveCatalog')->willReturn([$package]);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);
        $this->productPriceResolver->method('resolve')->willReturn(new ResolvedProductPrice(10.0, 'USD', null, null, null));
        $this->sysConfigRepo->method('findCachedValue')
            ->with(PackageCatalogResolver::MARGIN_CONFIG_KEY)
            ->willReturn('20');

        $offers = $this->resolver->catalogFor($account);

        $this->assertSame(12.0, $offers[0]->price);
    }

    public function testCatalogForExcludesPackagesWithNoProviderCoverage(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->packageRepository->method('findActiveCatalog')->willReturn([$package]);
        $this->productRepository->method('findMatchingDestination')->willReturn([]);

        $offers = $this->resolver->catalogFor($account);

        $this->assertSame([], $offers);
    }

    // ---- offerFor(): visibilidad restringida por contrato ----

    public function testOfferForReturnsNullWhenTenantHasContractsButNoneCoverThisPackage(): void
    {
        $account = $this->account(1);
        $contractedPackage = $this->package(1);
        $askedPackage = $this->package(2);
        $contract = $this->contract($contractedPackage, 15.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([$contract]);

        // Ni siquiera debería mirar la rama de MAX — la presencia de un
        // contrato restringe la visibilidad por completo.
        $this->productRepository->expects($this->never())->method('findMatchingDestination');

        $this->assertNull($this->resolver->offerFor($askedPackage, $account));
    }

    public function testOfferForReturnsNullWhenOnlyDefaultContractsExistForOtherPackages(): void
    {
        $account = $this->account(1);
        $contractedPackage = $this->package(1);
        $askedPackage = $this->package(2);
        $default = $this->contract($contractedPackage, 8.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([$default]);

        $this->assertNull($this->resolver->offerFor($askedPackage, $account));
    }

    public function testOfferForUnavailableWhenNoContractsAndNoProviderCoversTuple(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->productRepository->method('findMatchingDestination')->willReturn([]);

        $offer = $this->resolver->offerFor($package, $account);

        $this->assertNotNull($offer);
        $this->assertSame(PackageOfferSourceEnum::UNAVAILABLE, $offer->source);
    }

    // ---- offerForSale(): guardia de venta ----

    public function testOfferForSaleThrowsPackageNotVisibleWhenNotCoveredByContract(): void
    {
        $account = $this->account(1);
        $contractedPackage = $this->package(1);
        $askedPackage = $this->package(2);
        $contract = $this->contract($contractedPackage, 15.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([$contract]);

        try {
            $this->resolver->offerForSale($askedPackage, $account);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PACKAGE_NOT_VISIBLE_FOR_CLIENT', $e->getCodeWork());
        }
    }

    public function testOfferForSaleThrowsPackagePriceUnavailableWhenNoProviderCoversTuple(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);

        $this->contractRepository->method('findActiveForTenant')->willReturn([]);
        $this->contractRepository->method('findActiveDefaults')->willReturn([]);
        $this->productRepository->method('findMatchingDestination')->willReturn([]);

        try {
            $this->resolver->offerForSale($package, $account);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PACKAGE_PRICE_UNAVAILABLE', $e->getCodeWork());
        }
    }

    public function testOfferForSaleAdmitsAZeroPriceRealContract(): void
    {
        // Un contrato explícito a $0 es un precio real y deliberado — no
        // debe rechazarse, solo UNAVAILABLE/no-visible lo son.
        $account = $this->account(1);
        $package = $this->package(10);
        $contract = $this->contract($package, 0.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([$contract]);

        $offer = $this->resolver->offerForSale($package, $account);

        $this->assertSame(0.0, $offer->price);
        $this->assertSame(PackageOfferSourceEnum::TENANT_CONTRACT, $offer->source);
    }

    public function testOfferForSaleReturnsResolvedOfferWhenValid(): void
    {
        $account = $this->account(1);
        $package = $this->package(10);
        $contract = $this->contract($package, 15.0);

        $this->contractRepository->method('findActiveForTenant')->willReturn([$contract]);

        $offer = $this->resolver->offerForSale($package, $account);

        $this->assertSame(15.0, $offer->price);
    }
}
