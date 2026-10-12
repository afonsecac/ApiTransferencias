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
    /** Solo admin: interruptor de mercado de la cuenta. */
    public ?bool $marketEnabled = null;
    /** Solo admin: mercado realmente abierto (flag de la cuenta Y interruptor global). */
    public ?bool $marketOpen = null;
    public ?string $marketChangedAt = null;
    public ?string $marketChangedBy = null;
}
