<?php

namespace App\DTO\Out;

class ClientPackageOutDto
{
    public int $id;
    public ?string $name = null;
    public ?string $description = null;
    public float $amount;
    public string $currency;
    public ?string $activeStartAt = null;
    public ?string $activeEndAt = null;
    public ?TenantRefOutDto $tenant = null;
}
