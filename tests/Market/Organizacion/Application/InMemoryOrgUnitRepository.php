<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;

final class InMemoryOrgUnitRepository implements OrgUnitRepository
{
    /** @var array<int, OrgUnit> */
    public array $units = [];

    /** @var array<int, array<string, CostCenter>> por id de unidad y código */
    public array $centers = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function findById(int $id): ?OrgUnit
    {
        return $this->units[$id] ?? null;
    }

    public function findByTaxId(string $countryCode, string $taxId): ?OrgUnit
    {
        foreach ($this->units as $unit) {
            if ($unit->countryCode() === $countryCode && $unit->taxId() === $taxId) {
                return $unit;
            }
        }

        return null;
    }

    public function search(?OrgUnitType $type, ?string $query): array
    {
        return array_values(array_filter($this->units, function (OrgUnit $u) use ($type, $query): bool {
            if ($type !== null && !in_array($type, $u->types(), true)) {
                return false;
            }
            if ($query === null || $query === '') {
                return true;
            }
            foreach ([$u->name(), $u->legalName(), $u->taxId()] as $text) {
                if (stripos($text, $query) !== false) {
                    return true;
                }
            }
            foreach ($this->centers[$u->id()] ?? [] as $center) {
                if (stripos($center->code(), $query) !== false || stripos($center->name(), $query) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    public function save(OrgUnit $unit, array $costCenters = []): void
    {
        if ($unit->id() === null) {
            (new \ReflectionProperty(OrgUnit::class, 'id'))->setValue($unit, $this->nextId++);
        }
        $this->units[$unit->id()] = $unit;
        foreach ($costCenters as $center) {
            $this->centers[$unit->id()][$center->code()] = $center;
        }
        ++$this->saves;
    }

    public function costCentersOf(OrgUnit $unit): array
    {
        $centers = $this->centers[$unit->id()] ?? [];
        ksort($centers);

        return array_values($centers);
    }

    public function costCentersOfMany(array $unitIds): array
    {
        $result = [];
        foreach ($unitIds as $id) {
            $centers = $this->centers[$id] ?? [];
            ksort($centers);
            $result[$id] = array_values($centers);
        }

        return $result;
    }
}
