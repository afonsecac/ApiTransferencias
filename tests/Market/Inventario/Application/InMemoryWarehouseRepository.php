<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseRepository;

final class InMemoryWarehouseRepository implements WarehouseRepository
{
    /** @var array<int, Warehouse> */
    public array $items = [];
    private int $nextId = 1;

    public function findById(int $id): ?Warehouse
    {
        return $this->items[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Warehouse
    {
        foreach ($this->items as $warehouse) {
            if ($warehouse->slug()->value() === $slug) {
                return $warehouse;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function countByOrgUnit(int $orgUnitId): int
    {
        return count(array_filter($this->items, static fn (Warehouse $w) => $w->orgUnitId() === $orgUnitId));
    }

    public function save(Warehouse $warehouse): void
    {
        if ($warehouse->id() === null) {
            (new \ReflectionProperty($warehouse, 'id'))->setValue($warehouse, $this->nextId++);
        }
        $this->items[(int) $warehouse->id()] = $warehouse;
    }

    public function remove(Warehouse $warehouse): void
    {
        unset($this->items[(int) $warehouse->id()]);
    }
}
