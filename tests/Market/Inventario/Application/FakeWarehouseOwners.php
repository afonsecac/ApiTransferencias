<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseCostCenter;
use App\Market\Inventario\Domain\WarehouseOwner;
use App\Market\Inventario\Domain\WarehouseOwners;

final class FakeWarehouseOwners implements WarehouseOwners
{
    /** @var array<int, WarehouseOwner> */
    public array $owners = [];

    /** @param list<string> $types */
    public function add(int $id, string $name, array $types, int $principalCenter, ?int $inactiveCenter = null): void
    {
        $centers = [new WarehouseCostCenter($principalCenter, 'PRINCIPAL', 'Principal', true, true)];
        if ($inactiveCenter !== null) {
            $centers[] = new WarehouseCostCenter($inactiveCenter, 'OLD', 'Antiguo', false, false);
        }
        $this->owners[$id] = new WarehouseOwner($id, $name, $types, $centers);
    }

    public function find(int $orgUnitId): ?WarehouseOwner
    {
        return $this->owners[$orgUnitId] ?? null;
    }
}
