<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryCoverage;

final readonly class ServingWarehouse
{
    public function __construct(public DeliveryCoverage $coverage, public bool $tie)
    {
    }
}
