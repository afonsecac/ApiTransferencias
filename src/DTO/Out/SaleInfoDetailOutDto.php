<?php

namespace App\DTO\Out;

final class SaleInfoDetailOutDto extends SaleInfoListOutDto
{
    public ?string $updatedAt = null;
    public ?float $discount = null;
    public ?float $amountTax = null;
    public ?array $transactionStatus = null;
    public ?array $historical = null;

    /** Token único del comprobante público (GET /api/verify/{transactionId}) — habilita el botón "Ver comprobante" del dashboard. */
    public ?string $accessToken = null;
}
