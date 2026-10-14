<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
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
    public function __invoke(int $unitId, string $code, string $name): OrgUnit
    {
        $unit = $this->units->findById($unitId) ?? throw OrgUnitNotFound::forId($unitId);
        $unit->addCostCenter($code, $name);
        $this->units->save($unit);

        return $unit;
    }
}
