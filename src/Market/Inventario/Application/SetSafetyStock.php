<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockLineNotFound;
use App\Market\Inventario\Domain\StockProducts;
use App\Market\Inventario\Domain\WarehouseRepository;

final class SetSafetyStock
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseRepository $warehouses,
        private readonly StockProducts $products,
    ) {
    }

    /**
     * Fija el stock de seguridad de una línea existente (lo que no se vende para cubrir imprevistos).
     *
     * @throws InvalidStock
     * @throws StockLineNotFound
     */
    public function __invoke(int $warehouseId, string $itemType, int $itemId, int $safety): StockLineDetails
    {
        $type = StockItemType::tryFrom($itemType) ?? throw new InvalidStock(sprintf('El tipo "%s" no es válido: producto o combo.', $itemType));
        $line = $this->ledger->find($warehouseId, $type, $itemId) ?? throw StockLineNotFound::for($warehouseId, $itemId);
        $line->setSafety($safety);
        $this->ledger->save($line);

        return new StockLineDetails($line, $this->warehouses->findById($warehouseId), $this->products->find($itemId));
    }
}
