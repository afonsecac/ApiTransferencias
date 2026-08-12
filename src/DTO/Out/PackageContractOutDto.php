<?php

namespace App\DTO\Out;

final class PackageContractOutDto
{
    public int $id;
    public ?string $name = null;
    public ?string $description = null;
    public string $contractMode;
    public ?float $basePrice = null;
    public ?string $baseCurrency = null;
    public ?float $rateValue = null;
    public float $amount;
    public string $currency;
    public bool $isActive;
    public ?string $activeStartAt = null;
    public ?string $activeEndAt = null;
    public ?TenantRefOutDto $tenant = null;
    public ?int $referencePackageId = null;
}
