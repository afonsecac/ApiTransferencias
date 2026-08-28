<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\ClientProviderRoutingRepository;

/**
 * Criterio de "¿este cliente tiene un proveedor asignado a la categoría
 * (service/subservice) de este paquete?" — un cliente que YA configuró al
 * menos una fila de ClientProviderRouting deja de tener el fallback
 * implícito al proveedor por defecto (ver ProviderDispatchResolver): a
 * partir de ahí solo puede vender lo que tiene explícitamente cubierto por
 * routing, categoría por categoría.
 *
 * Mismo criterio de comodín que ProviderDispatchResolver::rowApplies()
 * aplicado SOLO a las dimensiones service/subservice (aquí no importa
 * environment/saleType, esto es visibilidad de catálogo, no despacho de una
 * venta concreta): serviceName/subserviceName NULL en la fila = comodín,
 * cubre cualquier valor de esa dimensión.
 *
 * Un cliente SIN ninguna fila de routing (routing no configurado para él en
 * absoluto) no se restringe — mantiene el comportamiento histórico de ver
 * todo el catálogo, igual que ProviderDispatchResolver cae al proveedor por
 * defecto en ese mismo caso.
 */
class ClientServiceProviderCoverageResolver
{
    public function __construct(
        private readonly ClientProviderRoutingRepository $routingRepo,
    ) {
    }

    public function isCoveredFor(Account $account, CommunicationPackage $package): bool
    {
        $client = $account->getClient();
        if ($client === null) {
            return true;
        }

        $rows = $this->routingRepo->findActiveRouteScopesForClient($client->getId());
        if ($rows === []) {
            return true;
        }

        $service = $package->getService();
        $packageServiceName = $service['name'] ?? null;
        $packageSubserviceName = $service['subservice']['name'] ?? null;

        foreach ($rows as $row) {
            if ($this->rowCoversCategory($row, $packageServiceName, $packageSubserviceName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{serviceName:?string, subserviceName:?string} $row
     */
    private function rowCoversCategory(array $row, ?string $packageServiceName, ?string $packageSubserviceName): bool
    {
        if ($row['serviceName'] !== null && trim($row['serviceName']) !== trim($packageServiceName ?? '')) {
            return false;
        }

        return $row['subserviceName'] === null || trim($row['subserviceName']) === trim($packageSubserviceName ?? '');
    }
}
