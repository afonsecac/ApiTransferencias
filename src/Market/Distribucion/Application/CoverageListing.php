<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

final readonly class CoverageListing
{
    /** @param list<CoverageRow> $rows */
    public function __construct(public string $currency, public array $rows)
    {
    }
}
