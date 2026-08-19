<?php

namespace App\DTO\Out;

final class ContractRangeResultOutDto
{
    public int $created;
    public int $updated;
    public int $skipped;
    /** @var int[] */
    public array $contractIds = [];
    /** @var float[] */
    public array $skippedAmounts = [];
}
