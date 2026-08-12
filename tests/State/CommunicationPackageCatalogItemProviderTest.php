<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\User;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\State\CommunicationPackageCatalogItemProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationPackageCatalogItemProvider
 */
class CommunicationPackageCatalogItemProviderTest extends TestCase
{
    private ProviderInterface&MockObject $innerProvider;
    private PackageCatalogResolver&MockObject $catalogResolver;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private Security&MockObject $security;
    private CommunicationPackageCatalogItemProvider $provider;

    protected function setUp(): void
    {
        $this->innerProvider = $this->createMock(ProviderInterface::class);
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        // Con vínculo por defecto — los tests existentes ejercitan el
        // camino de resolución normal, sin cambios.
        $this->bindingRepo->method('findAllForPackage')->willReturn([$this->createMock(CommunicationPackageProviderProduct::class)]);
        $this->security = $this->createMock(Security::class);

        $this->provider = new CommunicationPackageCatalogItemProvider($this->innerProvider, $this->catalogResolver, $this->bindingRepo, $this->security);
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())->setName('P')->setDescription('P')->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
    }

    public function testReturnsNullWhenInnerProviderFindsNoPackage(): void
    {
        $this->innerProvider->method('provide')->willReturn(null);
        $this->catalogResolver->expects($this->never())->method('offerFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoUserIsAuthenticated(): void
    {
        $this->innerProvider->method('provide')->willReturn($this->package());
        $this->security->method('getUser')->willReturn(null);
        $this->catalogResolver->expects($this->never())->method('offerFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenCurrentUserIsNotAnAccount(): void
    {
        $this->innerProvider->method('provide')->willReturn($this->package());
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->catalogResolver->expects($this->never())->method('offerFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPackageIsNotVisibleForTenant(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->catalogResolver->expects($this->once())
            ->method('offerFor')
            ->with($package, $account)
            ->willReturn(null);

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenOfferIsUnavailable(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 0.0, 'USD', PackageOfferSourceEnum::UNAVAILABLE)
        );

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPackageHasNoExplicitBinding(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 25.69, 'USD', PackageOfferSourceEnum::PRODUCT_MAX)
        );
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->bindingRepo->method('findAllForPackage')->willReturn([]);
        $this->provider = new CommunicationPackageCatalogItemProvider($this->innerProvider, $this->catalogResolver, $this->bindingRepo, $this->security);

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsThePackageWhenOfferIsResolved(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 25.69, 'USD', PackageOfferSourceEnum::PRODUCT_MAX)
        );

        $result = $this->provider->provide(new Get());

        $this->assertSame($package, $result);
    }
}
