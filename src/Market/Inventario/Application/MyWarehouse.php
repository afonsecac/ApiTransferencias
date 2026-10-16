<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseUserPermissions;

/** Un almacén al que llega un usuario, con lo que puede hacer en él. */
final readonly class MyWarehouse
{
    public function __construct(
        public Warehouse $warehouse,
        public WarehouseUserPermissions $permissions,
    ) {
    }
}
