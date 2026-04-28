<?php

namespace App\DTO\Out;

final class SaleRetryOutDto
{
    public int $id;
    public string $state;
    public bool $retryDispatched;
}
