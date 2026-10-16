<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryWarehouses;

/**
 * La consulta que Distribución ofrece a los demás contextos: qué almacén atiende un municipio. Solo cuentan los almacenes activos
 * (que pueden vender) con la fila de cobertura activa; gana la prioridad más baja y un empate se desempata por el menor id de
 * almacén (y se señala). Lo ya vendido no se vuelve a consultar contra esta regla.
 */
final class FindServingWarehouse
{
    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DeliveryWarehouses $warehouses,
    ) {
    }

    public function __invoke(int $municipalityId): ?ServingWarehouse
    {
        $sellable = $this->warehouses->sellableIds();
        $candidates = array_values(array_filter(
            $this->coverage->activeInMunicipality($municipalityId),
            static fn (DeliveryCoverage $c) => in_array($c->warehouseId(), $sellable, true),
        ));
        $primary = ListCoverage::primary($candidates);
        if ($primary === null) {
            return null;
        }

        return new ServingWarehouse($primary, count(array_filter($candidates, static fn (DeliveryCoverage $c) => $c->priority() === $primary->priority())) > 1);
    }
}
