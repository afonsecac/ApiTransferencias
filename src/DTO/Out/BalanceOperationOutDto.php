<?php

namespace App\DTO\Out;

final class BalanceOperationOutDto
{
    public ?int $id = null;
    public ?float $amount = null;
    public ?string $currency = null;
    public ?float $amountTax = null;
    public ?string $currencyTax = null;
    public ?float $discount = null;
    public ?string $currencyDiscount = null;
    public ?float $totalAmount = null;
    public ?string $totalCurrency = null;
    public ?array $tenant = null;
    public ?array $transfer = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $state = null;
    public ?string $operationType = null;
    public ?SaleInfoListOutDto $communicationSale = null;
    public ?string $disabledAt = null;
    public ?bool $isPreviousAmount = null;
    public ?bool $markAsReported = null;
    public ?string $reportedDateAt = null;
    public ?string $comment = null;
    public ?string $commentToImpugned = null;
}
