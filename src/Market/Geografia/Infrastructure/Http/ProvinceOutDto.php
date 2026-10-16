<?php

declare(strict_types=1);

namespace App\Market\Geografia\Infrastructure\Http;

use App\Market\Geografia\Application\ProvinceDetails;

/**
 * Respuesta de provincias (estructura de presentación, no es de entrada).
 */
class ProvinceOutDto
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public bool $special,
        public bool $active,
        public int $municipalityCount,
    ) {
    }

    public static function from(ProvinceDetails $details): self
    {
        $province = $details->province;

        return new self((int) $province->id(), $province->code(), $province->name(), $province->isSpecial(), $province->isActive(), $details->municipalityCount);
    }
}
