<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockMovement;

final class InMemoryStockLedger implements StockLedger
{
    /** @var array<string, StockLine> */
    public array $lines = [];
    /** @var list<StockMovement> */
    public array $movements = [];
    private int $nextId = 1;

    public function find(int $warehouseId, StockItemType $type, int $itemId): ?StockLine
    {
        return $this->lines[$warehouseId . ':' . $type->value . ':' . $itemId] ?? null;
    }

    public function lines(?int $warehouseId, ?StockItemType $type): array
    {
        return array_values(array_filter(
            $this->lines,
            static fn (StockLine $l) => ($warehouseId === null || $l->warehouseId() === $warehouseId) && ($type === null || $l->itemType() === $type),
        ));
    }

    public function movements(?int $warehouseId, ?int $itemId, int $limit): array
    {
        $found = array_values(array_filter(
            $this->movements,
            static fn (StockMovement $m) => ($warehouseId === null || $m->warehouseId() === $warehouseId) && ($itemId === null || $m->itemId() === $itemId),
        ));

        return array_slice(array_reverse($found), 0, $limit);
    }

    public function save(StockLine $line, ?StockMovement $movement = null): void
    {
        if ($line->id() === null) {
            (new \ReflectionProperty($line, 'id'))->setValue($line, $this->nextId++);
        }
        $this->lines[$line->warehouseId() . ':' . $line->itemType()->value . ':' . $line->itemId()] = $line;
        if ($movement !== null) {
            $this->movements[] = $movement;
        }
    }
}
