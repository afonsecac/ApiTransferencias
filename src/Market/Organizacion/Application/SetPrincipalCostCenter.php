<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenterSet;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class SetPrincipalCostCenter
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * @throws OrgUnitNotFound
     * @throws InvalidOrgUnit
     */
    public function __invoke(int $unitId, string $code): OrgUnitDetails
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);
        $set = new CostCenterSet($unit, $this->units->costCentersOf($unit));

        $changed = $set->makePrincipal($code);
        if ($changed !== []) {
            $this->units->save($unit, $changed);
        }

        return new OrgUnitDetails($unit, $set->all());
    }
}
