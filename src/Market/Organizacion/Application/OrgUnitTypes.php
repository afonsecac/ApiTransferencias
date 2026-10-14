<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitType;

final class OrgUnitTypes
{
    /**
     * @param list<string> $values
     * @return list<OrgUnitType>
     * @throws InvalidOrgUnit
     */
    public static function fromValues(array $values): array
    {
        return array_map(
            static fn (string $v) => OrgUnitType::tryFrom($v) ?? throw new InvalidOrgUnit(sprintf('Tipo de unidad desconocido: "%s".', $v)),
            $values,
        );
    }
}
