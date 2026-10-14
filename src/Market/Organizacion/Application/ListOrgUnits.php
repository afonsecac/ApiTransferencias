<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;

final class ListOrgUnits
{
    public function __construct(private readonly OrgUnitRepository $units)
    {
    }

    /**
     * El texto busca también en el código y el nombre de los centros de costo de cada unidad.
     *
     * @return list<OrgUnitDetails>
     */
    public function __invoke(?OrgUnitType $type = null, ?string $query = null): array
    {
        $units = $this->units->search($type, $query !== null ? trim($query) : null);
        $centers = $this->units->costCentersOfMany(array_map(static fn ($u) => (int) $u->id(), $units));

        return array_map(static fn ($u) => new OrgUnitDetails($u, $centers[(int) $u->id()] ?? []), $units);
    }
}
