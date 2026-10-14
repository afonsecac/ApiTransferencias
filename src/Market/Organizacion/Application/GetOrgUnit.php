<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnit;
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
    public function __invoke(int $id): OrgUnit
    {
        return $this->units->findById($id) ?? throw OrgUnitNotFound::forId($id);
    }
}
