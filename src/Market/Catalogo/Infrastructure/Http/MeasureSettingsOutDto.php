<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\MeasureSettingsDetails;

/**
 * Respuesta de la pantalla de medidas (estructura de presentación, no es de entrada). `baseLocked`: ya hay productos con
 * cantidades y las unidades base no se pueden cambiar.
 */
class MeasureSettingsOutDto
{
    public function __construct(
        public string $weightBase,
        public string $volumeBase,
        public int $volumetricDivisor,
        public bool $baseLocked,
    ) {
    }

    public static function from(MeasureSettingsDetails $details): self
    {
        return new self($details->weightBase->value, $details->volumeBase->value, $details->volumetricDivisor, $details->baseLocked);
    }
}
