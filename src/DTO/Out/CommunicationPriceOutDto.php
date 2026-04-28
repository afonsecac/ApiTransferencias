<?php

namespace App\DTO\Out;

final class CommunicationPriceOutDto
{
    public int $id;
    public float $startPrice;
    public ?float $endPrice = null;
    public string $currencyPrice;
    public float $amount;
    public string $currency;
    public bool $isActive;
    public ?string $validStartAt = null;
    public ?string $validEndAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
