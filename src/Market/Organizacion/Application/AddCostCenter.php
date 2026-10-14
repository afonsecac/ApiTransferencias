<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenterSet;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class AddCostCenter
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * @throws OrgUnitNotFound
     * @throws InvalidOrgUnit
     */
    public function __invoke(int $unitId, string $code, string $name): OrgUnitDetails
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);
        $set = new CostCenterSet($unit, $this->units->costCentersOf($unit));

        $new = $set->add($code, $name);
        $this->units->save($unit, [$new]);

        return new OrgUnitDetails($unit, $set->all());
    }
}
