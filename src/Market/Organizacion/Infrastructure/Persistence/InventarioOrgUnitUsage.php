<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Persistence;

use App\Market\Inventario\Application\CountWarehousesOfOwner;
use App\Market\Organizacion\Domain\OrgUnitUsage;

/**
 * Puerto síncrono explícito de Organización hacia Inventario (ADR-001): solo lectura y solo a través de su caso de uso.
 */
final class InventarioOrgUnitUsage implements OrgUnitUsage
{
    public function __construct(private readonly CountWarehousesOfOwner $countWarehouses)
    {
    }

    public function warehouseCount(int $orgUnitId): int
    {
        return ($this->countWarehouses)($orgUnitId);
    }
}
