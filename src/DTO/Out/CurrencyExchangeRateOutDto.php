<?php

namespace App\DTO\Out;

final class CurrencyExchangeRateOutDto
{
    public int $id;
    public string $baseCurrency;
    public string $targetCurrency;
    public float $rate;
    public string $rateDate;
    public string $fetchedAt;
}
