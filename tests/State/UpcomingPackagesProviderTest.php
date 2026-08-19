<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Catalog\UpcomingPackageCatalogResolver;
use App\Service\Pricing\PackageSalePriceResolver;
use App\State\UpcomingPackagesProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\UpcomingPackagesProvider
 */
class UpcomingPackagesProviderTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private PackageSalePriceResolver&MockObject $salePriceResolver;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CatalogVersionResolver $catalogVersion;
    private UpcomingPackageCatalogResolver&MockObject $upcomingResolver;
    private UpcomingPackagesProvider $provider;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        // CatalogVersionResolver es `final` — se construye real con
        // SysConfigRepository mockeado. Por defecto isV2() da false.
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->catalogVersion = new CatalogVersionResolver($this->sysConfigRepo);
        $this->upcomingResolver = $this->createMock(UpcomingPackageCatalogResolver::class);

        $this->provider = new UpcomingPackagesProvider(
            $this->em,
            $this->security,
            $this->salePriceResolver,
            $this->catalogVersion,
            $this->upcomingResolver,
        );
    }

    public function testReturnsEmptyArrayWhenUserIsNotAnAccountWithoutCallingIsV2(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->sysConfigRepo->expects($this->never())->method('findCachedValue');
        $this->em->expects($this->never())->method('getRepository');

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([], $result);
    }

    public function testV2DelegatesInTheUpcomingResolverWithoutTouchingTheEntityManager(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')
            ->with(CatalogVersionResolver::DEFAULT_VERSION_KEY)
            ->willReturn('v2');

        $v2Packages = [$this->createMock(CommunicationPackage::class)];
        $this->upcomingResolver->expects($this->once())->method('upcomingFor')->with($tenant)->willReturn($v2Packages);
        $this->em->expects($this->never())->method('getRepository');
        $this->em->expects($this->never())->method('createQueryBuilder');

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame($v2Packages, $result);
    }
}
