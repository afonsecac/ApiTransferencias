<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Inventario\Domain\WarehouseRepository;

final class GetWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseOwners $owners,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /** @throws WarehouseNotFound */
    public function __invoke(int $id): WarehouseDetails
    {
        $warehouse = $this->warehouses->findById($id) ?? throw WarehouseNotFound::forId($id);

        return WarehouseDetails::of($warehouse, $this->owners, $this->municipalities);
    }
}
