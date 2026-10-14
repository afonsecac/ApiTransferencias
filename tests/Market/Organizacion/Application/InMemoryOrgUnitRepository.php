<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;

final class InMemoryOrgUnitRepository implements OrgUnitRepository
{
    /** @var array<int, OrgUnit> */
    public array $units = [];

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
        return array_values(array_filter($this->units, static function (OrgUnit $u) use ($type, $query): bool {
            if ($type !== null && !in_array($type, $u->types(), true)) {
                return false;
            }

            return $query === null || $query === ''
                || stripos($u->name(), $query) !== false || stripos($u->legalName(), $query) !== false || stripos($u->taxId(), $query) !== false;
        }));
    }

    public function save(OrgUnit $unit): void
    {
        if ($unit->id() === null) {
            (new \ReflectionProperty(OrgUnit::class, 'id'))->setValue($unit, $this->nextId++);
        }
        $this->units[$unit->id()] = $unit;
        ++$this->saves;
    }
}
