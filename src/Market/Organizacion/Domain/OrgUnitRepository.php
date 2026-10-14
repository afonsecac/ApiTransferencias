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
     * @param string|null $query texto libre sobre nombre, razón social e identificador fiscal de la unidad y
     *                           sobre el código y el nombre de sus centros de costo
     * @return list<OrgUnit> por nombre
     */
    public function search(?OrgUnitType $type, ?string $query): array;

    /**
     * Guarda la unidad y los centros de costo dados en una sola operación (todo o nada).
     *
     * @param list<CostCenter> $costCenters los nuevos o los modificados
     */
    public function save(OrgUnit $unit, array $costCenters = []): void;

    /**
     * @return list<CostCenter> los centros de la unidad, por código
     */
    public function costCentersOf(OrgUnit $unit): array;

    /**
     * @param list<int> $unitIds
     * @return array<int, list<CostCenter>> los centros por id de unidad, por código
     */
    public function costCentersOfMany(array $unitIds): array;
}
