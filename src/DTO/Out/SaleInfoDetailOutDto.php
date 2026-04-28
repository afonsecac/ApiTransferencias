<?php

namespace App\DTO\Out;

final class SaleInfoDetailOutDto extends SaleInfoListOutDto
{
    public ?string $updatedAt = null;
    public ?float $discount = null;
    public ?float $amountTax = null;
    public ?array $transactionStatus = null;
    public ?array $historical = null;
}
