<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnitUsage;

final class InMemoryOrgUnitUsage implements OrgUnitUsage
{
    /** @var array<int, int> */
    public array $warehouses = [];

    public function warehouseCount(int $orgUnitId): int
    {
        return $this->warehouses[$orgUnitId] ?? 0;
    }
}
