<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryWarehouse;
use App\Market\Distribucion\Domain\DeliveryWarehouses;

final class FakeDeliveryWarehouses implements DeliveryWarehouses
{
    /** @var array<int, DeliveryWarehouse> */
    public array $items = [];

    public function add(int $id, string $name, string $status = 'active'): void
    {
        $this->items[$id] = new DeliveryWarehouse($id, $name, $status);
    }

    public function find(int $id): ?DeliveryWarehouse
    {
        return $this->items[$id] ?? null;
    }

    public function sellableIds(): array
    {
        return array_values(array_map(static fn (DeliveryWarehouse $w) => $w->id, array_filter($this->items, static fn (DeliveryWarehouse $w) => $w->status === 'active')));
    }
}
