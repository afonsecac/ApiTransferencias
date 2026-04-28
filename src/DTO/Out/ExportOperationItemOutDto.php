<?php

namespace App\DTO\Out;

final class ExportOperationItemOutDto
{
    public int $id;
    public float $amount;
    public string $currency;
    public string $operation_type;
    public string $date;
    public string $phone;
    public ?int $system_reference = null;
    public ?string $client_reference = null;
    public ?string $legacy_reference = null;
    public ?string $package = null;
}
