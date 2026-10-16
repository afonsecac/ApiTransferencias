<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockProduct;
use App\Market\Inventario\Domain\StockProducts;

final class FakeStockProducts implements StockProducts
{
    /** @var array<int, StockProduct> */
    public array $items = [];

    public function add(StockProduct $product): void
    {
        $this->items[$product->id] = $product;
    }

    public function find(int $id): ?StockProduct
    {
        return $this->items[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        return array_intersect_key($this->items, array_flip($ids));
    }
}
