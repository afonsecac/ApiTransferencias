<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

final readonly class QuotedLine
{
    public function __construct(
        public ?int $productId,
        public string $label,
        public string $basis,
        public ?int $quantity,
        public ?string $realKg,
        public ?string $volumetricKg,
        public string $billable,
        public string $rate,
        public string $amount,
    ) {
    }
}
