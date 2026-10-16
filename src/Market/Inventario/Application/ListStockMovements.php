<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockMovement;
use App\Market\Inventario\Domain\StockProducts;
use App\Market\Inventario\Domain\WarehouseRepository;

final class ListStockMovements
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseRepository $warehouses,
        private readonly StockProducts $products,
    ) {
    }

    /**
     * Los últimos movimientos, el más reciente primero.
     *
     * @return list<StockMovementDetails>
     */
    public function __invoke(?int $warehouseId, ?int $itemId, int $limit = 50): array
    {
        $movements = $this->ledger->movements($warehouseId, $itemId, max(1, min($limit, 200)));
        $products = $this->products->findMany(array_values(array_unique(array_map(static fn (StockMovement $m) => $m->itemId(), $movements))));
        $warehouses = [];
        foreach ($this->warehouses->findAll() as $warehouse) {
            $warehouses[(int) $warehouse->id()] = $warehouse;
        }

        return array_map(
            static fn (StockMovement $m) => new StockMovementDetails($m, $warehouses[$m->warehouseId()] ?? null, $products[$m->itemId()] ?? null),
            $movements,
        );
    }
}
