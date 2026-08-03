<?php

namespace App\Service\Provider;

final readonly class ExchangeRateSyncResult
{
    public function __construct(
        public int $created,
        public string $rateDate,
        public string $baseCurrency,
    ) {
    }
}
