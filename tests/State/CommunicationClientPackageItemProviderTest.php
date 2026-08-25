<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Repository\CommunicationPackageRepository;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use App\State\CommunicationClientPackageItemProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationClientPackageItemProvider
 */
class CommunicationClientPackageItemProviderTest extends TestCase
{
    private Security&MockObject $security;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CatalogPackageVisibilityResolver&MockObject $visibility;
    private CommunicationClientPackageItemProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->visibility = $this->createMock(CatalogPackageVisibilityResolver::class);

        $this->provider = new CommunicationClientPackageItemProvider(
            $this->security,
            $this->packageRepository,
            $this->visibility,
        );
    }

    public function testFetchesFromPackageRepositoryAndAppliesVisibility(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);

        $package = $this->createMock(CommunicationPackage::class);
        $this->packageRepository->expects($this->once())->method('find')->with(42)->willReturn($package);
        $this->visibility->method('visibleFor')->with($package, $tenant)->willReturn($package);

        $result = $this->provider->provide(new Get(), ['id' => '42']);

        $this->assertSame($package, $result);
    }

    public function testReturnsNullWhenVisibilityResolverRejectsThePackage(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);

        $package = $this->createMock(CommunicationPackage::class);
        $this->packageRepository->method('find')->willReturn($package);
        $this->visibility->method('visibleFor')->willReturn(null);

        $result = $this->provider->provide(new Get(), ['id' => '42']);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPackageDoesNotExist(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($tenant);

        $this->packageRepository->method('find')->willReturn(null);
        $this->visibility->expects($this->never())->method('visibleFor');

        $result = $this->provider->provide(new Get(), ['id' => '999']);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenIdIsMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(Account::class));
        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get(), []);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenIdIsNotNumeric(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(Account::class));
        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get(), ['id' => 'not-a-number']);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenUserIsNotAnAccount(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->packageRepository->expects($this->never())->method('find');

        $result = $this->provider->provide(new Get(), ['id' => '42']);

        $this->assertNull($result);
    }
}
