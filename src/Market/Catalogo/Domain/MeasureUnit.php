<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Unidades en las que un vendedor puede introducir una cantidad (COMR-141). Solo se convierten entre unidades del
 * mismo tipo: peso con peso, volumen con volumen.
 */
enum MeasureUnit: string
{
    case G = 'g';
    case KG = 'kg';
    case OZ = 'oz';
    case LB = 'lb';
    case ML = 'ml';
    case L = 'l';
    case FL_OZ = 'fl_oz';
    case UNIT = 'unit';

    public function kind(): MeasureKind
    {
        return match ($this) {
            self::G, self::KG, self::OZ, self::LB => MeasureKind::WEIGHT,
            self::ML, self::L, self::FL_OZ => MeasureKind::VOLUME,
            self::UNIT => MeasureKind::COUNT,
        };
    }

    /** Cuántas unidades base (g, ml, unidades) vale una de esta unidad. */
    public function factor(): float
    {
        return match ($this) {
            self::G, self::ML, self::UNIT => 1.0,
            self::KG, self::L => 1000.0,
            self::OZ => 28.349523125,
            self::LB => 453.59237,
            self::FL_OZ => 29.5735295625,
        };
    }
}
