<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
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
    public function __invoke(int $unitId, string $code): OrgUnit
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);
        $unit->makePrincipal($code);
        $this->units->save($unit);

        return $unit;
    }
}
