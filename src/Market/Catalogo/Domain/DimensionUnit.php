<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La unidad en que se escribieron las dimensiones de un producto (COMR-281). Se guardan siempre en milímetros; la unidad se
 * recuerda aparte para mostrárselas a la persona tal como las introdujo, igual que la unidad original de las cantidades.
 */
enum DimensionUnit: string
{
    case MM = 'mm';
    case CM = 'cm';
    case M = 'm';
    case IN = 'in';
    case FT = 'ft';

    /** Cuántos milímetros vale una de esta unidad. */
    public function factor(): float
    {
        return match ($this) {
            self::MM => 1.0,
            self::CM => 10.0,
            self::M => 1000.0,
            self::IN => 25.4,
            self::FT => 304.8,
        };
    }

    public function toMm(float $value): float
    {
        return round($value * $this->factor(), 4);
    }

    public function fromMm(float $mm): float
    {
        return round($mm / $this->factor(), 4);
    }
}
