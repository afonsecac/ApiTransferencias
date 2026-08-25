<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Service\Catalog\UpcomingPackageCatalogResolver;
use App\State\UpcomingPackagesProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\UpcomingPackagesProvider
 */
class UpcomingPackagesProviderTest extends TestCase
{
    private Security&MockObject $security;
    private UpcomingPackageCatalogResolver&MockObject $upcomingResolver;
    private UpcomingPackagesProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->upcomingResolver = $this->createMock(UpcomingPackageCatalogResolver::class);

        $this->provider = new UpcomingPackagesProvider(
            $this->security,
            $this->upcomingResolver,
        );
    }

    public function testReturnsEmptyArrayWhenUserIsNotAnAccount(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->upcomingResolver->expects($this->never())->method('upcomingFor');

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame([], $result);
    }

    public function testDelegatesInTheUpcomingResolver(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);

        $v2Packages = [$this->createMock(CommunicationPackage::class)];
        $this->upcomingResolver->expects($this->once())->method('upcomingFor')->with($tenant)->willReturn($v2Packages);

        $result = $this->provider->provide(new GetCollection());

        $this->assertSame($v2Packages, $result);
    }
}
