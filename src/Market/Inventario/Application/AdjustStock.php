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
     * Fija lo en mano en un NUEVO VALOR (como el tablero: «Nuevo valor en mano»), con motivo y comentario obligatorios, y deja un
     * movimiento inmutable con la diferencia, el usuario y los centros de costo del propietario (vendedor del producto) y del
     * custodio (dueño del almacén). La diferencia se calcula con lo que hay en el momento de guardar. El primer valor crea la
     * línea. No puede dejar lo en mano por debajo de lo reservado ni dejarlo igual.
     *
     * @throws InvalidStock
     * @throws StockWarehouseInvalid un borrador no admite stock
     * @throws StockProductInvalid
     * @throws StockBelowReserved
     */
    public function __invoke(int $warehouseId, string $itemType, int $itemId, int $newOnHand, string $reason, string $comment, ?string $reference, StockActor $actor): StockLineDetails
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

        if ($newOnHand < 0) {
            throw new InvalidStock('El nuevo valor en mano no puede ser negativo.');
        }
        $line = $this->ledger->find($warehouseId, $type, $itemId) ?? StockLine::open($warehouseId, $type, $itemId);
        $delta = $newOnHand - $line->onHand();
        if ($delta === 0) {
            throw new InvalidStock('El nuevo valor es igual al que ya hay en mano: no hay nada que ajustar.');
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
