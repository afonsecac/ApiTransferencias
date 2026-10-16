<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\GiftCardProducts;

final class FakeGiftCardProducts implements GiftCardProducts
{
    /** @var array<int, GiftCardProduct> */
    public array $items = [];

    public function add(GiftCardProduct $product): void
    {
        $this->items[$product->id] = $product;
    }

    public function find(int $id): ?GiftCardProduct
    {
        return $this->items[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        return array_intersect_key($this->items, array_flip($ids));
    }
}
