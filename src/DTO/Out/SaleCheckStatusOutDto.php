<?php

namespace App\DTO\Out;

final class SaleCheckStatusOutDto
{
    public int $id;
    public string $state;
    public ?string $transactionStatus = null;
    public ?string $stateProcess = null;
    public ?string $message = null;
}
