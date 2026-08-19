<?php

namespace App\DTO\Out;

final class ContractBatchResultOutDto
{
    public int $created;
    public int $updated;
    /** @var int[] */
    public array $accountIds = [];
}
