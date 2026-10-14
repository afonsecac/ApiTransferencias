<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\Market\Organizacion\Domain\FiscalField;
use App\Market\Organizacion\Domain\FiscalProfile;

/**
 * Respuesta de los endpoints de registros fiscales (estructura de presentación, no es de entrada).
 */
class FiscalProfileOutDto
{
    /**
     * @param list<array<string, mixed>> $fields
     */
    public function __construct(
        public string $countryCode,
        public string $name,
        public bool $generic,
        public array $fields,
        public string $updatedAt,
    ) {
    }

    public static function from(FiscalProfile $profile): self
    {
        return new self(
            $profile->countryCode(),
            $profile->name(),
            $profile->countryCode() === FiscalProfile::GENERIC_CODE,
            array_map(static fn (FiscalField $f) => $f->toArray(), $profile->fields()),
            $profile->updatedAt()->format(DATE_ATOM),
        );
    }
}
