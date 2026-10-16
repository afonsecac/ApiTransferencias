<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

final readonly class BulkResult
{
    public function __construct(public int $create, public int $update, public int $skipped, public bool $applied)
    {
    }
}
