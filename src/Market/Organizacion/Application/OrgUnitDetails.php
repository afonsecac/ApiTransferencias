<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnit;

/**
 * Una unidad organizativa junto con sus centros de costo: lo que devuelven los casos de uso.
 */
final readonly class OrgUnitDetails
{
    /**
     * @param list<CostCenter> $costCenters
     */
    public function __construct(public OrgUnit $unit, public array $costCenters)
    {
    }
}
