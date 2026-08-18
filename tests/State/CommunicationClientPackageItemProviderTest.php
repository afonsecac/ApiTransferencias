<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Pricing\PriceSourceEnum;
use App\Service\Pricing\ResolvedSalePrice;
use App\State\CommunicationClientPackageItemProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationClientPackageItemProvider
 */
class CommunicationClientPackageItemProviderTest extends TestCase
{
    private ProviderInterface&MockObject $itemProvider;
    private Security&MockObject $security;
    private PackageSalePriceResolver&MockObject $salePriceResolver;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CatalogVersionResolver $catalogVersion;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CatalogPackageVisibilityResolver&MockObject $visibility;
    private CommunicationClientPackageItemProvider $provider;

    protected function setUp(): void
    {
        $this->itemProvider = $this->createMock(ProviderInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        // CatalogVersionResolver es `final` — se construye real con
        // SysConfigRepository mockeado (mismo patrón que
        // CommunicationClientPackageProviderTest). Por defecto isV2() da
        // false.
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->catalogVersion = new CatalogVersionResolver($this->sysConfigRepo);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->visibility = $this->createMock(CatalogPackageVisibilityResolver::class);

        $this->provider = new CommunicationClientPackageItemProvider(
            $this->itemProvider,
            $this->security,
            $this->salePriceResolver,
            $this->catalogVersion,
            $this->packageRepository,
            $this->visibility,
        );
    }

    // ---- Rama V1 (comportamiento intacto) ----

    public function testV1DelegatesInInnerProviderAndResolvesSalePrice(): void
    {
        $package = $this->createMock(CommunicationClientPackage::class);
        $tenant = $this->createMock(Account::class);

        $this->itemProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($tenant);
        $this->salePriceResolver->expects($this->once())->method('resolve')->with($package, $tenant)
            ->willReturn(new ResolvedSalePrice(10.0, 'USD', PriceSourceEnum::CONTRACT));
        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get());

        $this->assertSame($package, $result);
    }

    public function testV1ReturnsNullFromInnerProviderUntouched(): void
    {
        $this->itemProvider->method('provide')->willReturn(null);
        $this->security->method('getUser')->willReturn($this->createMock(Account::class));

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    // ---- Rama V2 ----

    public function testV2FetchesFromPackageRepositoryInsteadOfInnerProvider(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')
            ->with(CatalogVersionResolver::DEFAULT_VERSION_KEY)
            ->willReturn('v2');

        $package = $this->createMock(CommunicationPackage::class);
        $this->packageRepository->expects($this->once())->method('find')->with(42)->willReturn($package);
        $this->visibility->method('visibleFor')->with($package, $tenant)->willReturn($package);

        $this->itemProvider->expects($this->never())->method('provide');
        $this->salePriceResolver->expects($this->never())->method('resolve');

        $result = $this->provider->provide(new Get(), ['id' => '42']);

        $this->assertSame($package, $result);
    }

    public function testV2ReturnsNullWhenVisibilityResolverRejectsThePackage(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')->willReturn('v2');

        $package = $this->createMock(CommunicationPackage::class);
        $this->packageRepository->method('find')->willReturn($package);
        $this->visibility->method('visibleFor')->willReturn(null);

        $result = $this->provider->provide(new Get(), ['id' => '42']);

        $this->assertNull($result);
    }

    public function testV2ReturnsNullWhenPackageDoesNotExist(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')->willReturn('v2');

        $this->packageRepository->method('find')->willReturn(null);
        $this->visibility->expects($this->never())->method('visibleFor');

        $result = $this->provider->provide(new Get(), ['id' => '999']);

        $this->assertNull($result);
    }

    public function testV2ReturnsNullWhenIdIsMissing(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')->willReturn('v2');

        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get(), []);

        $this->assertNull($result);
    }

    public function testV2ReturnsNullWhenIdIsNotNumeric(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')->willReturn('v2');

        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get(), ['id' => 'not-a-number']);

        $this->assertNull($result);
    }
}
