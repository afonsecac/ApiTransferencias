<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

interface OrgUnitRepository
{
    public function findById(int $id): ?OrgUnit;

    /**
     * @param string $taxId ya normalizado (mayúsculas y sin espacios en los extremos)
     */
    public function findByTaxId(string $countryCode, string $taxId): ?OrgUnit;

    /**
     * @param string|null $query texto libre sobre nombre, razón social e identificador fiscal
     * @return list<OrgUnit> por nombre
     */
    public function search(?OrgUnitType $type, ?string $query): array;

    public function save(OrgUnit $unit): void;
}
