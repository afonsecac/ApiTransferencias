<?php

namespace App\DTO\Out;

final class UncertainSaleOutDto
{
    public int $id;
    public ?string $transactionId = null;
    /** recharge | package */
    public string $type;
    public ?string $provider = null;
    public ?int $accountId = null;
    public float $reservedAmount;
    public ?string $currency = null;
    public string $createdAt;
    public int $ageMinutes;
    public int $checkAttempts;
    public ?string $nextCheckAt = null;
    /** @var array{outcome: ?string, code: ?string, message: ?string} */
    public array $lastStatus;
    public ?string $reason = null;
}
