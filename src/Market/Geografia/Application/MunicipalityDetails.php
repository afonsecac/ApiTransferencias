<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\Municipality;
use App\Market\Geografia\Domain\Province;

final class MunicipalityDetails
{
    /** Se puede elegir y entregar: el municipio y su provincia están activos. */
    public readonly bool $selectable;

    public function __construct(public readonly Municipality $municipality, public readonly Province $province)
    {
        $this->selectable = $municipality->isActive() && $province->isActive();
    }
}
