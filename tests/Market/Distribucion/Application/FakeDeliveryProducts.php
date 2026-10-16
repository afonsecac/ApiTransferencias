<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryProduct;
use App\Market\Distribucion\Domain\DeliveryProducts;

final class FakeDeliveryProducts implements DeliveryProducts
{
    /** @var array<int, DeliveryProduct> */
    public array $items = [];

    public function add(DeliveryProduct $product): void
    {
        $this->items[$product->id] = $product;
    }

    public function find(int $id): ?DeliveryProduct
    {
        return $this->items[$id] ?? null;
    }
}
