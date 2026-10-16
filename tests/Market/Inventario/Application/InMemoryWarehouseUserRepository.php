<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class InMemoryWarehouseUserRepository implements WarehouseUserRepository
{
    /** @var array<string, WarehouseUser> */
    public array $items = [];
    private int $nextId = 1;

    public function find(int $userId, int $warehouseId): ?WarehouseUser
    {
        return $this->items[$userId . ':' . $warehouseId] ?? null;
    }

    public function ofWarehouse(int $warehouseId): array
    {
        return array_values(array_filter($this->items, static fn (WarehouseUser $l) => $l->warehouseId() === $warehouseId));
    }

    public function ofUser(int $userId): array
    {
        return array_values(array_filter($this->items, static fn (WarehouseUser $l) => $l->userId() === $userId));
    }

    public function save(WarehouseUser $link): void
    {
        if ($link->id() === null) {
            (new \ReflectionProperty($link, 'id'))->setValue($link, $this->nextId++);
        }
        $this->items[$link->userId() . ':' . $link->warehouseId()] = $link;
    }
}
