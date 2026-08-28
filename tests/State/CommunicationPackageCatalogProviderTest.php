<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Catalog\ClientServiceProviderCoverageResolver;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\State\CommunicationPackageCatalogProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\State\CommunicationPackageCatalogProvider
 */
class CommunicationPackageCatalogProviderTest extends TestCase
{
    private PackageCatalogResolver&MockObject $catalogResolver;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private Security&MockObject $security;
    private BenefitOperationResolver&MockObject $benefitResolver;
    private ClientServiceProviderCoverageResolver&MockObject $coverageResolver;
    private CommunicationPackageCatalogProvider $provider;

    protected function setUp(): void
    {
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->benefitResolver = $this->createMock(BenefitOperationResolver::class);
        $this->benefitResolver->method('resolve')->willReturnCallback(static fn (CommunicationPackage $p) => $p->getBenefits());
        $this->coverageResolver = $this->createMock(ClientServiceProviderCoverageResolver::class);
        $this->coverageResolver->method('isCoveredFor')->willReturn(true);

        $this->provider = new CommunicationPackageCatalogProvider($this->catalogResolver, $this->bindingRepo, $this->security, $this->benefitResolver, $this->coverageResolver);
    }

    private function packageWithId(int $id, string $name): CommunicationPackage
    {
        $package = (new CommunicationPackage())->setName($name)->setDescription($name)->setDestinationAmount(1.0)->setDestinationCurrency('CUP');
        $property = new \ReflectionProperty($package, 'id');
        $property->setAccessible(true);
        $property->setValue($package, $id);

        return $package;
    }

    /**
     * ResolvedPackageOffer no setea resolvedOffer en el paquete por sí solo
     * (lo hace PackageCatalogResolver::catalogFor() de verdad, mockeado
     * aquí) — sin esto, getAmount() devolvería null y el filtro/orden por
     * precio no tendría nada que comparar.
     */
    private function offer(CommunicationPackage $package, float $price): ResolvedPackageOffer
    {
        $offer = new ResolvedPackageOffer($package, $price, 'USD', PackageOfferSourceEnum::PRODUCT_MAX);
        $package->setResolvedOffer($offer);

        return $offer;
    }

    private function requestContext(array $query): array
    {
        return ['request' => new Request($query)];
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

    public function testExcludesPackagesNotCoveredByAnyClientProviderRouting(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $covered = $this->packageWithId(1, 'Covered');
        $notCovered = $this->packageWithId(2, 'NotCovered');

        $this->catalogResolver->method('catalogFor')->willReturn([
            new ResolvedPackageOffer($covered, 10.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
            new ResolvedPackageOffer($notCovered, 20.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
        ]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);
        $this->coverageResolver = $this->createMock(ClientServiceProviderCoverageResolver::class);
        $this->coverageResolver->method('isCoveredFor')->willReturnCallback(
            static fn (Account $a, CommunicationPackage $p) => $p->getId() === 1
        );
        $this->provider = new CommunicationPackageCatalogProvider($this->catalogResolver, $this->bindingRepo, $this->security, $this->benefitResolver, $this->coverageResolver);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([$covered], $result);
    }

    public function testWithoutRequestInContextReturnsAPlainArraySortedById(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $p2 = $this->packageWithId(2, 'B');
        $p1 = $this->packageWithId(1, 'A');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($p2, 20.0), $this->offer($p1, 10.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([$p1, $p2], $result);
    }

    public function testFiltersOutPackagesBelowTheMinimumPrice(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $cheap = $this->packageWithId(1, 'Cheap');
        $expensive = $this->packageWithId(2, 'Expensive');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($cheap, 10.0), $this->offer($expensive, 50.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = iterator_to_array($this->provider->provide(new GetCollection(), context: $this->requestContext(['price' => ['gte' => '20']])));

        $this->assertSame([$expensive], array_values($result));
    }

    public function testFiltersOutPackagesAboveTheMaximumPrice(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $cheap = $this->packageWithId(1, 'Cheap');
        $expensive = $this->packageWithId(2, 'Expensive');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($cheap, 10.0), $this->offer($expensive, 50.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = iterator_to_array($this->provider->provide(new GetCollection(), context: $this->requestContext(['price' => ['lte' => '20']])));

        $this->assertSame([$cheap], array_values($result));
    }

    public function testOrdersByPriceAscendingWhenRequested(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $expensive = $this->packageWithId(1, 'Expensive');
        $cheap = $this->packageWithId(2, 'Cheap');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($expensive, 50.0), $this->offer($cheap, 10.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = iterator_to_array($this->provider->provide(new GetCollection(), context: $this->requestContext(['orderBy' => ['price' => 'asc']])));

        $this->assertSame([$cheap, $expensive], array_values($result));
    }

    public function testOrdersByPriceDescendingWhenRequested(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $expensive = $this->packageWithId(1, 'Expensive');
        $cheap = $this->packageWithId(2, 'Cheap');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($cheap, 10.0), $this->offer($expensive, 50.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2]);

        $result = iterator_to_array($this->provider->provide(new GetCollection(), context: $this->requestContext(['orderBy' => ['price' => 'desc']])));

        $this->assertSame([$expensive, $cheap], array_values($result));
    }

    public function testWithoutOrderByDefaultsToOrderingByIdAscendingEvenWithARequestPresent(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $p3 = $this->packageWithId(3, 'C');
        $p1 = $this->packageWithId(1, 'A');
        $p2 = $this->packageWithId(2, 'B');
        $this->catalogResolver->method('catalogFor')->willReturn([$this->offer($p3, 5.0), $this->offer($p1, 50.0), $this->offer($p2, 30.0)]);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2, 3]);

        $result = iterator_to_array($this->provider->provide(new GetCollection(), context: $this->requestContext([])));

        $this->assertSame([$p1, $p2, $p3], array_values($result));
    }

    public function testPaginatesUsingPageAndItemsPerPage(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $packages = [$this->packageWithId(1, 'A'), $this->packageWithId(2, 'B'), $this->packageWithId(3, 'C')];
        $offers = array_map(fn (CommunicationPackage $p) => $this->offer($p, 10.0), $packages);
        $this->catalogResolver->method('catalogFor')->willReturn($offers);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2, 3]);

        $operation = (new GetCollection())->withPaginationItemsPerPage(2)->withPaginationClientItemsPerPage(true);
        $result = $this->provider->provide($operation, context: $this->requestContext(['page' => '2']));

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertSame(3.0, $result->getTotalItems());
        $this->assertSame([$packages[2]], array_values(iterator_to_array($result)));
    }

    public function testPaginationClampsItemsPerPageToTheDeclaredMaximum(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $packages = [$this->packageWithId(1, 'A'), $this->packageWithId(2, 'B'), $this->packageWithId(3, 'C')];
        $offers = array_map(fn (CommunicationPackage $p) => $this->offer($p, 10.0), $packages);
        $this->catalogResolver->method('catalogFor')->willReturn($offers);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([1, 2, 3]);

        $operation = (new GetCollection())->withPaginationClientItemsPerPage(true)->withPaginationMaximumItemsPerPage(1);
        $result = $this->provider->provide($operation, context: $this->requestContext(['itemsPerPage' => '50']));

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertSame(1.0, $result->getItemsPerPage());
    }
}
