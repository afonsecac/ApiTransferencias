<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\CatalogPackageVisibilityResolver
 */
class CatalogPackageVisibilityResolverTest extends TestCase
{
    private PackageCatalogResolver&MockObject $catalogResolver;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private BenefitOperationResolver&MockObject $benefitResolver;
    private CatalogPackageVisibilityResolver $resolver;

    protected function setUp(): void
    {
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->bindingRepo->method('findAllForPackage')->willReturn([$this->createMock(CommunicationPackageProviderProduct::class)]);
        $this->benefitResolver = $this->createMock(BenefitOperationResolver::class);
        $this->benefitResolver->method('resolve')->willReturnCallback(static fn (CommunicationPackage $p) => $p->getBenefits());

        $this->resolver = new CatalogPackageVisibilityResolver($this->catalogResolver, $this->bindingRepo, $this->benefitResolver);
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())->setName('P')->setDescription('P')->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
    }

    public function testReturnsNullWhenPackageIsNotVisibleForTenant(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->catalogResolver->expects($this->once())
            ->method('offerFor')
            ->with($package, $account)
            ->willReturn(null);

        $this->assertNull($this->resolver->visibleFor($package, $account));
    }

    public function testReturnsNullWhenOfferIsUnavailable(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 0.0, 'USD', PackageOfferSourceEnum::UNAVAILABLE)
        );

        $this->assertNull($this->resolver->visibleFor($package, $account));
    }

    public function testReturnsNullWhenPackageHasNoExplicitBinding(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 25.69, 'USD', PackageOfferSourceEnum::PRODUCT_MAX)
        );
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->bindingRepo->method('findAllForPackage')->willReturn([]);
        $this->resolver = new CatalogPackageVisibilityResolver($this->catalogResolver, $this->bindingRepo, $this->benefitResolver);

        $this->assertNull($this->resolver->visibleFor($package, $account));
    }

    public function testReturnsNullWhenPackageIsOutsideItsOwnActiveWindow(): void
    {
        $now = new \DateTimeImmutable();
        $package = $this->package()->setActiveStartAt($now->modify('+1 day'));
        $account = $this->createMock(Account::class);

        $this->catalogResolver->expects($this->never())->method('offerFor');

        $this->assertNull($this->resolver->visibleFor($package, $account, $now));
    }

    public function testReturnsThePackageWhenOfferIsResolvedAndWithinWindow(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->catalogResolver->method('offerFor')->willReturn(
            new ResolvedPackageOffer($package, 25.69, 'USD', PackageOfferSourceEnum::PRODUCT_MAX)
        );

        $this->assertSame($package, $this->resolver->visibleFor($package, $account));
    }
}
