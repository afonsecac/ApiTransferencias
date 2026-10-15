<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Cambiar las unidades base con productos que ya tienen cantidades guardadas reinterpretaría todos sus datos (lo guardado en kg
 * se leería como g), así que se rechaza.
 */
final class MeasureSettingsLocked extends \DomainException
{
    public static function withProducts(): self
    {
        return new self('Las unidades base no se pueden cambiar mientras haya productos con cantidades guardadas: sus datos se reinterpretarían.');
    }
}
