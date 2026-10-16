<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseMunicipality;

final class FakeWarehouseMunicipalities implements WarehouseMunicipalities
{
    /** @var array<int, WarehouseMunicipality> */
    public array $items = [];

    public function add(int $id, string $name, string $province = 'La Habana', bool $selectable = true, string $code = ''): void
    {
        $this->items[$id] = new WarehouseMunicipality($id, $code !== '' ? $code : strtolower($name), $name, $province, $selectable);
    }

    public function find(int $id): ?WarehouseMunicipality
    {
        return $this->items[$id] ?? null;
    }

    public function findByCode(string $code): ?WarehouseMunicipality
    {
        foreach ($this->items as $item) {
            if ($item->code === $code) {
                return $item;
            }
        }

        return null;
    }
}
