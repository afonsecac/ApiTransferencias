<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseRepository;

/** Cuántos almacenes tiene una unidad organizativa: lo consulta Organización para no dejarla sin tipo válido. */
final class CountWarehousesOfOwner
{
    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    public function __invoke(int $orgUnitId): int
    {
        return $this->warehouses->countByOrgUnit($orgUnitId);
    }
}
