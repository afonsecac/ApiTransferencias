<?php

namespace App\DTO\Out;

final class RechargeVerificationOutDto
{
    public string $transactionId;
    public ?string $etecsaOrderId = null;
    public ?int $clientId = null;
    public ?string $enteredAt = null;
    public string $state;
    public ?string $phoneMasked = null;
    public ?string $package = null;
    public ?float $destinationAmount = null;
    public ?string $destinationCurrency = null;

    /** @var array{name: string, description: ?string}|null */
    public ?array $promotion = null;

    /** @var array<int, array{state: string, occurredAt: ?string}> */
    public array $history = [];
}
