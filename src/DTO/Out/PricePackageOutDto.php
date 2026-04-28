<?php

namespace App\DTO\Out;

final class PricePackageOutDto
{
    public int $id;
    public ?string $name = null;
    public ?string $description = null;
    public float $price;
    public string $priceCurrency;
    public float $amount;
    public string $currency;
    public bool $isActive;
    public ?string $activeStartAt = null;
    public ?string $activeEndAt = null;
    public ?TenantRefOutDto $tenant = null;
    public ?EnvironmentRefOutDto $environment = null;
    public ?ProductRefOutDto $product = null;
}
