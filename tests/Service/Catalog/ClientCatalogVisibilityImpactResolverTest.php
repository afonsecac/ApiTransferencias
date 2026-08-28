<?php

namespace App\Tests\Service\Catalog;

use App\Entity\CommunicationPackage;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationPackageRepository;
use App\Service\Catalog\ClientCatalogVisibilityImpactResolver;
use App\Service\Catalog\ClientServiceProviderCoverageResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\ClientCatalogVisibilityImpactResolver
 */
class ClientCatalogVisibilityImpactResolverTest extends TestCase
{
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private CommunicationPackageRepository&MockObject $packageRepo;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private ClientServiceProviderCoverageResolver&MockObject $coverageResolver;
    private ClientCatalogVisibilityImpactResolver $resolver;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->packageRepo = $this->createMock(CommunicationPackageRepository::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->coverageResolver = $this->createMock(ClientServiceProviderCoverageResolver::class);

        $this->resolver = new ClientCatalogVisibilityImpactResolver(
            $this->routingRepo,
            $this->packageRepo,
            $this->bindingRepo,
            $this->coverageResolver,
        );
    }

    private function packageWithId(int $id): CommunicationPackage
    {
        $package = (new CommunicationPackage())->setName('P')->setDescription('P')->setDestinationAmount(1.0)->setDestinationCurrency('CUP');
        $property = new \ReflectionProperty($package, 'id');
        $property->setAccessible(true);
        $property->setValue($package, $id);

        return $package;
    }

    private function stubUniverse(CommunicationPackage ...$packages): void
    {
        $this->packageRepo->method('findActiveCatalog')->willReturn($packages);
        $this->bindingRepo->method('findPackageIdsWithBindings')
            ->willReturn(array_map(static fn (CommunicationPackage $p) => (int) $p->getId(), $packages));
    }

    public function testReturnsZeroWhenClientHasNoRoutingAndTheProposedRowIsWildcard(): void
    {
        $p1 = $this->packageWithId(1);
        $this->stubUniverse($p1);
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([]);

        // Sin filas -> "antes" cubre todo (isCoveredByRows([]) === true).
        // "después" con una sola fila comodín (sin service) también cubre todo.
        $this->coverageResolver->method('isCoveredByRows')->willReturnCallback(
            static fn (array $rows) => $rows === [] || $rows[0]['serviceName'] === null
        );

        $count = $this->resolver->countNewlyHiddenPackages(
            clientId: 1,
            excludeRoutingId: null,
            proposedServiceName: null,
            proposedSubserviceName: null,
            proposedIsActive: true,
        );

        $this->assertSame(0, $count);
    }

    public function testCountsPackagesThatWereVisibleAndWouldBecomeHidden(): void
    {
        $covered = $this->packageWithId(1);
        $notCovered = $this->packageWithId(2);
        $this->stubUniverse($covered, $notCovered);
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([]);

        // "antes" (sin filas) siempre visible. "después" (con la fila
        // propuesta) solo cubre el paquete 1.
        $this->coverageResolver->method('isCoveredByRows')->willReturnCallback(
            static fn (array $rows, CommunicationPackage $p) => $rows === [] || (int) $p->getId() === 1
        );

        $count = $this->resolver->countNewlyHiddenPackages(
            clientId: 1,
            excludeRoutingId: null,
            proposedServiceName: 'Mobile',
            proposedSubserviceName: null,
            proposedIsActive: true,
        );

        $this->assertSame(1, $count);
    }

    public function testDoesNotCountPackagesThatWereAlreadyHiddenBeforeTheChange(): void
    {
        $alreadyHidden = $this->packageWithId(1);
        $this->stubUniverse($alreadyHidden);
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 5, 'serviceName' => 'Utilities', 'subserviceName' => null],
        ]);

        // Ya estaba oculto antes (rows no vacío, coverage false) -> no debe
        // contar como "recién oculto", aunque tampoco esté cubierto después.
        $this->coverageResolver->method('isCoveredByRows')->willReturn(false);

        $count = $this->resolver->countNewlyHiddenPackages(
            clientId: 1,
            excludeRoutingId: null,
            proposedServiceName: 'Mobile',
            proposedSubserviceName: null,
            proposedIsActive: true,
        );

        $this->assertSame(0, $count);
    }

    public function testEditingAnExistingRowExcludesItFromTheBeforeAndAfterBaseWhenSimulatingReplacement(): void
    {
        $package = $this->packageWithId(1);
        $this->stubUniverse($package);
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 5, 'serviceName' => 'Mobile', 'subserviceName' => null],
        ]);

        $seenRowSets = [];
        $this->coverageResolver->method('isCoveredByRows')->willReturnCallback(
            function (array $rows) use (&$seenRowSets) {
                $seenRowSets[] = $rows;

                return true;
            }
        );

        $this->resolver->countNewlyHiddenPackages(
            clientId: 1,
            excludeRoutingId: 5,
            proposedServiceName: 'Utilities',
            proposedSubserviceName: null,
            proposedIsActive: true,
        );

        // "antes": la fila persistida tal cual (incluye la #5 original, Mobile).
        $this->assertSame([['id' => 5, 'serviceName' => 'Mobile', 'subserviceName' => null]], $seenRowSets[0]);
        // "después": la #5 reemplazada por la propuesta (Utilities), no las dos.
        $this->assertSame([['serviceName' => 'Utilities', 'subserviceName' => null]], $seenRowSets[1]);
    }

    public function testTogglingAnExistingRowToInactiveDropsItFromTheAfterScenario(): void
    {
        $package = $this->packageWithId(1);
        $this->stubUniverse($package);
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 5, 'serviceName' => 'Mobile', 'subserviceName' => null],
        ]);

        $seenRowSets = [];
        $this->coverageResolver->method('isCoveredByRows')->willReturnCallback(
            function (array $rows) use (&$seenRowSets) {
                $seenRowSets[] = $rows;

                return true;
            }
        );

        $this->resolver->countNewlyHiddenPackages(
            clientId: 1,
            excludeRoutingId: 5,
            proposedServiceName: 'Mobile',
            proposedSubserviceName: null,
            proposedIsActive: false,
        );

        $this->assertSame([], $seenRowSets[1]);
    }
}
