<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

interface DeliveryCurrencies
{
    public const DEFAULT = 'EUR';

    public function currencyOf(int $warehouseId): string;

    public function set(int $warehouseId, string $currency): void;
}
