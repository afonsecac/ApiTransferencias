<?php

namespace App\Service\Provider;

final readonly class ResolvedExchangeRate
{
    public function __construct(
        public float $rate,
        public ?\DateTimeImmutable $rateDate,
    ) {
    }
}
