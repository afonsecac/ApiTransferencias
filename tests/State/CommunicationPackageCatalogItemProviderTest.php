<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\User;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use App\State\CommunicationPackageCatalogItemProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationPackageCatalogItemProvider
 *
 * Desde la extracción de CatalogPackageVisibilityResolver, este provider
 * solo hace fetch + chequeo de Account + delegación — el criterio de
 * visibilidad en sí (offerFor/binding/ventana activa) se prueba en
 * tests/Service/Catalog/CatalogPackageVisibilityResolverTest.php.
 */
class CommunicationPackageCatalogItemProviderTest extends TestCase
{
    private ProviderInterface&MockObject $innerProvider;
    private CatalogPackageVisibilityResolver&MockObject $visibility;
    private Security&MockObject $security;
    private CommunicationPackageCatalogItemProvider $provider;

    protected function setUp(): void
    {
        $this->innerProvider = $this->createMock(ProviderInterface::class);
        $this->visibility = $this->createMock(CatalogPackageVisibilityResolver::class);
        $this->security = $this->createMock(Security::class);

        $this->provider = new CommunicationPackageCatalogItemProvider($this->innerProvider, $this->visibility, $this->security);
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())->setName('P')->setDescription('P')->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
    }

    public function testReturnsNullWhenInnerProviderFindsNoPackage(): void
    {
        $this->innerProvider->method('provide')->willReturn(null);
        $this->visibility->expects($this->never())->method('visibleFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoUserIsAuthenticated(): void
    {
        $this->innerProvider->method('provide')->willReturn($this->package());
        $this->security->method('getUser')->willReturn(null);
        $this->visibility->expects($this->never())->method('visibleFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenCurrentUserIsNotAnAccount(): void
    {
        $this->innerProvider->method('provide')->willReturn($this->package());
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->visibility->expects($this->never())->method('visibleFor');

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsNullWhenVisibilityResolverRejectsThePackage(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->visibility->expects($this->once())
            ->method('visibleFor')
            ->with($package, $account)
            ->willReturn(null);

        $result = $this->provider->provide(new Get());

        $this->assertNull($result);
    }

    public function testReturnsThePackageWhenVisibilityResolverAcceptsIt(): void
    {
        $package = $this->package();
        $account = $this->createMock(Account::class);

        $this->innerProvider->method('provide')->willReturn($package);
        $this->security->method('getUser')->willReturn($account);
        $this->visibility->method('visibleFor')->with($package, $account)->willReturn($package);

        $result = $this->provider->provide(new Get());

        $this->assertSame($package, $result);
    }
}
