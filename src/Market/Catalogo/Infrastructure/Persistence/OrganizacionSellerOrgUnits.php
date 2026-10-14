<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\SellerCostCenter;
use App\Market\Catalogo\Domain\SellerOrgUnit;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Organizacion\Application\GetOrgUnit;
use App\Market\Organizacion\Application\OrgUnitDetails;
use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitType;

/**
 * Puerto síncrono explícito de Catálogo hacia Organización (ADR-001): solo lectura, y solo a través del caso de
 * uso GetOrgUnit de Organización, nunca de su Infrastructure ni de sus repositorios. Un vendedor necesita saber si
 * su unidad existe, si es de tipo vendedor y qué centros de costo tiene.
 */
final class OrganizacionSellerOrgUnits implements SellerOrgUnits
{
    public function __construct(private readonly GetOrgUnit $getOrgUnit)
    {
    }

    public function find(int $orgUnitId): ?SellerOrgUnit
    {
        try {
            return $this->translate(($this->getOrgUnit)($orgUnitId));
        } catch (OrgUnitNotFound) {
            return null;
        }
    }

    public function findMany(array $orgUnitIds): array
    {
        $units = [];
        foreach ($orgUnitIds as $id) {
            $unit = $this->find($id);
            if ($unit !== null) {
                $units[$id] = $unit;
            }
        }

        return $units;
    }

    private function translate(OrgUnitDetails $details): SellerOrgUnit
    {
        return new SellerOrgUnit(
            (int) $details->unit->id(),
            $details->unit->name(),
            array_map(static fn (OrgUnitType $t) => $t->value, $details->unit->types()),
            array_map(static fn (CostCenter $c) => new SellerCostCenter((int) $c->id(), $c->code(), $c->name(), $c->isPrincipal(), $c->isActive()), $details->costCenters),
        );
    }
}
