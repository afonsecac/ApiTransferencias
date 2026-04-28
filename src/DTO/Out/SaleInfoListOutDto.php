<?php

namespace App\DTO\Out;

class SaleInfoListOutDto
{
    public ?int $id = null;
    public ?string $transactionOrder = null;
    public ?string $transactionId = null;
    public ?string $createdAt = null;
    public ?string $clientTransactionId = null;
    public ?float $amount = null;
    public ?string $currency = null;
    public ?float $totalPrice = null;
    public ?string $state = null;
    public ?string $type = null;
}
