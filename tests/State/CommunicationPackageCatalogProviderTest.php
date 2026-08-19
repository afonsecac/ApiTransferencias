<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\State\CommunicationPackageCatalogProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationPackageCatalogProvider
 */
class CommunicationPackageCatalogProviderTest extends TestCase
{
    private PackageCatalogResolver&MockObject $catalogResolver;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private Security&MockObject $security;
    private BenefitOperationResolver&MockObject $benefitResolver;
    private CommunicationPackageCatalogProvider $provider;

    protected function setUp(): void
    {
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->benefitResolver = $this->createMock(BenefitOperationResolver::class);
        $this->benefitResolver->method('resolve')->willReturnCallback(static fn (CommunicationPackage $p) => $p->getBenefits());

        $this->provider = new CommunicationPackageCatalogProvider($this->catalogResolver, $this->bindingRepo, $this->security, $this->benefitResolver);
    }

    private function packageWithId(int $id, string $name): CommunicationPackage
    {
        $package = (new CommunicationPackage())->setName($name)->setDescription($name)->setDestinationAmount(1.0)->setDestinationCurrency('CUP');
        $property = new \ReflectionProperty($package, 'id');
        $property->setAccessible(true);
        $property->setValue($package, $id);

        return $package;
    }

    public function testReturnsEmptyArrayWhenCurrentUserIsNotAnAccount(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->catalogResolver->expects($this->never())->method('catalogFor');

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenNoUserIsAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([], $result);
    }

    public function testMapsResolvedOffersToTheirPackagesWhenBothAreBound(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $package1 = $this->packageWithId(1, 'A');
        $package2 = $this->packageWithId(2, 'B');

        $this->catalogResolver->expects($this->once())
            ->method('catalogFor')
            ->with($account)
            ->willReturn([
                new ResolvedPackageOffer($package1, 10.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
                new ResolvedPackageOffer($package2, 20.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
            ]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->with([1, 2])->willReturn([1, 2]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([$package1, $package2], $result);
    }

    public function testExcludesPackagesWithoutAnyExplicitBinding(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $bound = $this->packageWithId(1, 'Bound');
        $unbound = $this->packageWithId(2, 'Unbound');

        $this->catalogResolver->method('catalogFor')->willReturn([
            new ResolvedPackageOffer($bound, 10.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
            new ResolvedPackageOffer($unbound, 20.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
        ]);
        // Solo el paquete 1 tiene vínculo explícito.
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([$bound], $result);
    }

    public function testExcludesPackagesOutsideTheirOwnActiveWindow(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $now = new \DateTimeImmutable();
        $active = $this->packageWithId(1, 'Active');
        $future = $this->packageWithId(2, 'Future')->setActiveStartAt($now->modify('+1 day'));

        $this->catalogResolver->method('catalogFor')->willReturn([
            new ResolvedPackageOffer($active, 10.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
            new ResolvedPackageOffer($future, 20.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
        ]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([$active], $result);
    }
}
