<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Qué se mide: peso, volumen o conteo. Cada tipo tiene una unidad base en la que se guarda y se calcula todo (MeasureSystem).
 */
enum MeasureKind: string
{
    case WEIGHT = 'weight';
    case VOLUME = 'volume';
    case COUNT = 'count';
}
