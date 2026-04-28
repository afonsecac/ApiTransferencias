<?php

namespace App\DTO\Out;

final class AccountSecOutDto
{
    public int $id;
    public ?string $accessToken = null;
    public ?string $origin = null;
    public ?string $contractCurrency = null;
    public ?float $minBalance = null;
    public ?float $criticalBalance = null;
    public ?array $environment = null;
    public ?bool $isActive = null;
    public ?float $discount = null;
    public ?float $commission = null;
}
