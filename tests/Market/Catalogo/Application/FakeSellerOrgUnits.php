<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\SellerOrgUnit;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerCostCenter;

final class FakeSellerOrgUnits implements SellerOrgUnits
{
    /** @var array<int, SellerOrgUnit> */
    public array $units = [];

    /**
     * @param list<string> $types
     * @param list<SellerCostCenter>|null $costCenters por defecto uno principal y activo con id = 10 × la unidad
     */
    public function add(int $id, string $name = 'Unidad', array $types = ['seller'], ?array $costCenters = null): void
    {
        $this->units[$id] = new SellerOrgUnit(
            $id,
            $name,
            $types,
            $costCenters ?? [new SellerCostCenter($id * 10, 'PRINCIPAL', 'Centro principal', true, true)],
        );
    }

    public function find(int $orgUnitId): ?SellerOrgUnit
    {
        return $this->units[$orgUnitId] ?? null;
    }

    public function findMany(array $orgUnitIds): array
    {
        return array_intersect_key($this->units, array_flip($orgUnitIds));
    }
}
