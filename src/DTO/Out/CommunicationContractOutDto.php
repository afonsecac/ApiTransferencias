<?php

namespace App\DTO\Out;

final class CommunicationContractOutDto
{
    public int $id;
    public int $communicationPackageId;
    public ?string $communicationPackageName = null;
    public ?TenantRefOutDto $tenant = null;
    public float $destinationAmount;
    public string $destinationCurrency;
    public float $price;
    public string $currency;
    public ?string $startAt = null;
    public ?string $endAt = null;
    public bool $isActive;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $createdByEmail = null;
}
