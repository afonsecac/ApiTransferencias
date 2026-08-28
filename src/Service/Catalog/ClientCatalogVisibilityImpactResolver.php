<?php

namespace App\Service\Catalog;

use App\Entity\CommunicationPackage;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationPackageRepository;

/**
 * Impacto en visibilidad de catálogo de crear/editar una fila de
 * ClientProviderRouting ANTES de guardarla — para el panel de "vista previa
 * de impacto" del formulario de routing (dashboard-cm). Simula, con la
 * misma lógica de ClientServiceProviderCoverageResolver::isCoveredByRows(),
 * dos escenarios sobre las filas activas actuales del cliente:
 *
 *  - "antes": las filas tal cual están persistidas hoy.
 *  - "después": las mismas filas, salvo que (edición) la fila
 *    $excludeRoutingId se reemplaza por los valores propuestos, o
 *    (alta) se añade la fila propuesta — solo si $proposedIsActive es true;
 *    si es false (desactivar/editar a inactiva), simplemente se omite.
 *
 * El conteo son los paquetes que eran visibles ANTES y dejarían de serlo
 * DESPUÉS — nunca cuenta un paquete que ya estaba oculto (por otra
 * categoría sin cobertura), porque eso no es un efecto NUEVO de este
 * cambio.
 *
 * Universo de paquetes: CommunicationPackageRepository::findActiveCatalog()
 * (activos, dentro de ventana) con vínculo explícito a producto
 * (CommunicationPackageProviderProductRepository::findPackageIdsWithBindings())
 * — el mismo conjunto base que ve un cliente SIN contrato restrictivo (ver
 * CommunicationPackageCatalogProvider). Deliberadamente NO resuelve
 * contratos por tenant (requeriría un Account, no solo un Client, y esos
 * son ortogonales a esta gate) — es una aproximación pensada para alertar
 * al admin, no un espejo exacto del catálogo resuelto de cada cuenta.
 */
class ClientCatalogVisibilityImpactResolver
{
    public function __construct(
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly CommunicationPackageRepository $packageRepo,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly ClientServiceProviderCoverageResolver $coverageResolver,
    ) {
    }

    public function countNewlyHiddenPackages(
        int $clientId,
        ?int $excludeRoutingId,
        ?string $proposedServiceName,
        ?string $proposedSubserviceName,
        bool $proposedIsActive,
        ?\DateTimeImmutable $now = null,
    ): int {
        $now ??= new \DateTimeImmutable();

        $beforeRows = $this->routingRepo->findActiveRouteScopesForClient($clientId);

        $afterRows = array_values(array_filter(
            $beforeRows,
            static fn (array $row) => $row['id'] !== $excludeRoutingId,
        ));
        if ($proposedIsActive) {
            $afterRows[] = ['serviceName' => $proposedServiceName, 'subserviceName' => $proposedSubserviceName];
        }

        $newlyHidden = 0;
        foreach ($this->universePackages($now) as $package) {
            if (!$this->coverageResolver->isCoveredByRows($beforeRows, $package)) {
                continue; // ya estaba oculto, no es un efecto nuevo de este cambio
            }

            if (!$this->coverageResolver->isCoveredByRows($afterRows, $package)) {
                $newlyHidden++;
            }
        }

        return $newlyHidden;
    }

    /**
     * @return list<CommunicationPackage>
     */
    private function universePackages(\DateTimeImmutable $now): array
    {
        $packages = $this->packageRepo->findActiveCatalog($now);
        $ids = array_map(static fn (CommunicationPackage $p) => (int) $p->getId(), $packages);
        $boundIds = $this->bindingRepo->findPackageIdsWithBindings($ids);

        return array_values(array_filter($packages, static fn (CommunicationPackage $p) => in_array($p->getId(), $boundIds, true)));
    }
}
