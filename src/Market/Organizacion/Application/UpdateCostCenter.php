<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class UpdateCostCenter
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * Cambia el nombre y activa o desactiva. El código no cambia y un centro no se borra.
     *
     * @throws OrgUnitNotFound
     * @throws InvalidOrgUnit
     */
    public function __invoke(int $unitId, string $code, string $name, bool $active): OrgUnit
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);

        // Se valida todo antes de guardar: si desactivar falla, el nombre tampoco se cambia.
        $unit->renameCostCenter($code, $name);
        $active ? $unit->activateCostCenter($code) : $unit->deactivateCostCenter($code);
        $this->units->save($unit);

        return $unit;
    }
}
