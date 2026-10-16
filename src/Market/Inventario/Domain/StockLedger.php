<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

interface StockLedger
{
    public function find(int $warehouseId, StockItemType $type, int $itemId): ?StockLine;

    /** @return list<StockLine> */
    public function lines(?int $warehouseId, ?StockItemType $type): array;

    /** @return list<StockMovement> los más recientes primero */
    public function movements(?int $warehouseId, ?int $itemId, int $limit): array;

    /** Guarda la línea y, en la misma operación, su movimiento: o se guardan los dos o ninguno. */
    public function save(StockLine $line, ?StockMovement $movement = null): void;
}
