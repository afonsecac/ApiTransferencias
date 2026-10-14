<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Qué se mide: peso, volumen o conteo. Cada tipo tiene una unidad base en la que se guarda y se calcula todo.
 */
enum MeasureKind: string
{
    case WEIGHT = 'weight';
    case VOLUME = 'volume';
    case COUNT = 'count';

    public function baseUnit(): MeasureUnit
    {
        return match ($this) {
            self::WEIGHT => MeasureUnit::G,
            self::VOLUME => MeasureUnit::ML,
            self::COUNT => MeasureUnit::UNIT,
        };
    }
}
