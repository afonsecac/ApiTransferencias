<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\Province;

final class ProvinceDetails
{
    public function __construct(public readonly Province $province, public readonly int $municipalityCount)
    {
    }
}
