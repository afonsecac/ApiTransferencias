<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Persistence;

use App\Market\Distribucion\Domain\DeliveryWarehouse;
use App\Market\Distribucion\Domain\DeliveryWarehouses;
use App\Market\Inventario\Application\GetWarehouse;
use App\Market\Inventario\Application\SellableWarehouseIds;
use App\Market\Inventario\Domain\WarehouseNotFound;

/**
 * Puerto síncrono explícito de Distribución hacia Inventario (ADR-001): solo lectura y solo a través de sus casos de uso.
 */
final class InventarioDeliveryWarehouses implements DeliveryWarehouses
{
    public function __construct(
        private readonly GetWarehouse $getWarehouse,
        private readonly SellableWarehouseIds $sellable,
    ) {
    }

    public function find(int $id): ?DeliveryWarehouse
    {
        try {
            $warehouse = ($this->getWarehouse)($id)->warehouse;
        } catch (WarehouseNotFound) {
            return null;
        }

        return new DeliveryWarehouse((int) $warehouse->id(), $warehouse->name(), $warehouse->status()->value);
    }

    public function sellableIds(): array
    {
        return ($this->sellable)();
    }
}
