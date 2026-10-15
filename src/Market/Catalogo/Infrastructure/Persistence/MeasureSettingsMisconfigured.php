<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

/**
 * Falta o es inválida una clave de la configuración de medidas. Es un fallo de despliegue (migración sin aplicar o valor
 * editado a mano), no un error de quien usa la pantalla.
 */
final class MeasureSettingsMisconfigured extends \RuntimeException
{
    public static function forKey(string $key, ?string $value, string $expected): self
    {
        return new self(sprintf('La configuración "%s" vale %s y debe ser %s.', $key, $value === null ? '(ausente)' : '"' . $value . '"', $expected));
    }
}
