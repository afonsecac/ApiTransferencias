<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Unidades en las que un vendedor puede introducir una cantidad (COMR-141). Solo se convierten entre unidades del
 * mismo tipo: peso con peso, volumen con volumen. Lo que se guarda se expresa en la unidad base que dice la configuración
 * (MeasureSystem); aquí cada unidad sabe cuánto vale en kilogramos, litros o unidades.
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

    /** Cuánto vale una de esta unidad en kilogramos (peso), litros (volumen) o unidades (conteo). */
    public function factor(): float
    {
        return match ($this) {
            self::KG, self::L, self::UNIT => 1.0,
            self::G, self::ML => 0.001,
            self::OZ => 0.028349523125,
            self::LB => 0.45359237,
            self::FL_OZ => 0.0295735295625,
        };
    }
}
