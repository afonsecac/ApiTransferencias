<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenterSet;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
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
    public function __invoke(int $unitId, string $code, string $name, bool $active): OrgUnitDetails
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);
        $set = new CostCenterSet($unit, $this->units->costCentersOf($unit));

        // Todo se valida antes de guardar: si desactivar falla, el nombre tampoco se guarda.
        $center = $set->rename($code, $name);
        $active ? $set->activate($code) : $set->deactivate($code);
        $this->units->save($unit, [$center]);

        return new OrgUnitDetails($unit, $set->all());
    }
}
