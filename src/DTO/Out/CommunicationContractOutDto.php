<?php

namespace App\DTO\Out;

final class CommunicationContractOutDto
{
    public int $id;

    /**
     * @var list<CommunicationPackageRefOutDto>
     */
    public array $packages = [];

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
