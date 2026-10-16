<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryCurrencies;

final class InMemoryDeliveryCurrencies implements DeliveryCurrencies
{
    /** @var array<int, string> */
    public array $items = [];

    public function currencyOf(int $warehouseId): string
    {
        return $this->items[$warehouseId] ?? DeliveryCurrencies::DEFAULT;
    }

    public function set(int $warehouseId, string $currency): void
    {
        $this->items[$warehouseId] = $currency;
    }
}
