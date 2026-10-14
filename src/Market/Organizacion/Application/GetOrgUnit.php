<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class GetOrgUnit
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * @throws OrgUnitNotFound
     */
    public function __invoke(int $id): OrgUnitDetails
    {
        $unit = $this->units->findById($id) ?? throw OrgUnitNotFound::forId($id);

        return new OrgUnitDetails($unit, $this->units->costCentersOf($unit));
    }
}
