<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockActor;
use App\Market\Inventario\Domain\StockBelowReserved;
use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockMovement;
use App\Market\Inventario\Domain\StockProductInvalid;
use App\Market\Inventario\Domain\StockProducts;
use App\Market\Inventario\Domain\StockReason;
use App\Market\Inventario\Domain\StockWarehouseInvalid;
use App\Market\Inventario\Domain\WarehouseRepository;

final class AdjustStock
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseRepository $warehouses,
        private readonly StockProducts $products,
    ) {
    }

    /**
     * Suma o resta existencias con motivo y comentario obligatorios y deja un movimiento inmutable con el usuario y los centros
     * de costo del propietario (vendedor del producto) y del custodio (dueño del almacén). La primera entrada positiva crea la
     * línea. No puede dejar lo en mano por debajo de lo reservado.
     *
     * @throws InvalidStock
     * @throws StockWarehouseInvalid un borrador no admite stock
     * @throws StockProductInvalid
     * @throws StockBelowReserved
     */
    public function __invoke(int $warehouseId, string $itemType, int $itemId, int $delta, string $reason, string $comment, ?string $reference, StockActor $actor): StockLineDetails
    {
        $type = StockItemType::tryFrom($itemType) ?? throw new InvalidStock(sprintf('El tipo "%s" no es válido: producto o combo.', $itemType));
        if ($type !== StockItemType::Product) {
            throw StockProductInvalid::comboNotAvailable();
        }
        $reasonEnum = StockReason::tryFrom($reason) ?? throw new InvalidStock('El motivo es obligatorio y debe ser uno de la lista.');

        $warehouse = $this->warehouses->findById($warehouseId) ?? throw StockWarehouseInvalid::unknown($warehouseId);
        if ($warehouse->status()->value === 'draft') {
            throw StockWarehouseInvalid::draft($warehouse->name());
        }
        $product = $this->products->find($itemId) ?? throw StockProductInvalid::unknown($itemId);
        if (!$product->physical) {
            throw StockProductInvalid::digital($product->name);
        }

        $line = $this->ledger->find($warehouseId, $type, $itemId);
        if ($line === null) {
            if ($delta <= 0) {
                throw StockBelowReserved::of(0, $delta, 0);
            }
            $line = StockLine::open($warehouseId, $type, $itemId);
        }

        // Se valida el comentario antes de tocar la línea: un rechazo no deja el stock a medias.
        $probe = clone $line;
        $probe->adjust($delta);
        StockMovement::adjustment($probe, $delta, $reasonEnum, $comment, $reference, $actor, $product->sellerCostCenterId, (int) $warehouse->costCenterId());

        $line->adjust($delta);
        $movement = StockMovement::adjustment($line, $delta, $reasonEnum, $comment, $reference, $actor, $product->sellerCostCenterId, (int) $warehouse->costCenterId());
        $this->ledger->save($line, $movement);

        return new StockLineDetails($line, $warehouse, $product);
    }
}
