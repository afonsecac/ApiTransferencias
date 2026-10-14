<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;

final class ListOrgUnits
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * @return list<OrgUnit>
     */
    public function __invoke(?OrgUnitType $type = null, ?string $query = null): array
    {
        return $this->units->search($type, $query !== null ? trim($query) : null);
    }
}
