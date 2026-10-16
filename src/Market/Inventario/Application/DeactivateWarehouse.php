<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;

final class DeactivateWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseOwners $owners,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /**
     * Deja de vender y de ofrecerse, pero conserva sus datos; lo ya vendido sigue su curso.
     *
     * @throws WarehouseNotFound
     * @throws MainWarehouseProtected
     * @throws WarehouseTransitionInvalid
     */
    public function __invoke(int $id): WarehouseDetails
    {
        $warehouse = $this->warehouses->findById($id) ?? throw WarehouseNotFound::forId($id);
        $warehouse->deactivate();
        $this->warehouses->save($warehouse);

        return WarehouseDetails::of($warehouse, $this->owners, $this->municipalities);
    }
}
