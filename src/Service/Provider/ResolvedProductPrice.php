<?php

namespace App\Service\Provider;

final readonly class ResolvedProductPrice
{
    public function __construct(
        public float $amount,
        public ?string $currency,
        public ?float $conversionRate,
        public ?\DateTimeImmutable $conversionRateDate,
        public ?string $pendingNote,
    ) {
    }
}
