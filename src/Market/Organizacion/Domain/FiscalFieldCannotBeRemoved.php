<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Los campos de un registro fiscal se desactivan, no se borran: las unidades ya guardadas pueden tener datos en ellos.
 */
final class FiscalFieldCannotBeRemoved extends InvalidFiscalProfile
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('El campo fiscal "%s" no se puede borrar, solo desactivar.', $key));
    }
}
