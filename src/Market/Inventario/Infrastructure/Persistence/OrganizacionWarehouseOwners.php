<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Inventario\Domain\WarehouseCostCenter;
use App\Market\Inventario\Domain\WarehouseOwner;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Organizacion\Application\GetOrgUnit;
use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitType;

/**
 * Puerto síncrono explícito de Inventario hacia Organización (ADR-001): solo lectura y solo a través del caso de uso GetOrgUnit.
 */
final class OrganizacionWarehouseOwners implements WarehouseOwners
{
    public function __construct(private readonly GetOrgUnit $getOrgUnit)
    {
    }

    public function find(int $orgUnitId): ?WarehouseOwner
    {
        try {
            $details = ($this->getOrgUnit)($orgUnitId);
        } catch (OrgUnitNotFound) {
            return null;
        }

        return new WarehouseOwner(
            (int) $details->unit->id(),
            $details->unit->name(),
            array_map(static fn (OrgUnitType $t) => $t->value, $details->unit->types()),
            array_map(static fn (CostCenter $c) => new WarehouseCostCenter((int) $c->id(), $c->code(), $c->name(), $c->isPrincipal(), $c->isActive()), $details->costCenters),
        );
    }
}
