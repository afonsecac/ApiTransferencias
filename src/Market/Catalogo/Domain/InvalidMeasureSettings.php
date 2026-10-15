<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Unas medidas que no cuadran: una base que no es del tipo que le corresponde, una unidad que no existe o un divisor no positivo.
 */
final class InvalidMeasureSettings extends \DomainException
{
}
