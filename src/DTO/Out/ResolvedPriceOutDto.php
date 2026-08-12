<?php

namespace App\DTO\Out;

final class ResolvedPriceOutDto
{
    public float $amount;
    public string $currency;
    public string $source;
    public ?int $contractId = null;
    public ?string $note = null;
}
