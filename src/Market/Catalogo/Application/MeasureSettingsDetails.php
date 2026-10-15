<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\MeasureUnit;

/**
 * Las medidas del catálogo tal como las ve la pantalla: las unidades base, el divisor y si la base está bloqueada porque ya hay
 * productos con cantidades.
 */
final readonly class MeasureSettingsDetails
{
    public function __construct(
        public MeasureUnit $weightBase,
        public MeasureUnit $volumeBase,
        public int $volumetricDivisor,
        public bool $baseLocked,
    ) {
    }
}
