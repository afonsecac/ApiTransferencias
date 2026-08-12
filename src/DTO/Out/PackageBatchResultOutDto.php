<?php

namespace App\DTO\Out;

final class PackageBatchResultOutDto
{
    public int $created;
    /** @var int[] */
    public array $packageIds = [];
}
