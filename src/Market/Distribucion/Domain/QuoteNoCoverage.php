<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class QuoteNoCoverage extends \DomainException
{
    public static function in(string $municipality): self
    {
        return new self(sprintf('Ningún almacén cubre el municipio "%s": no se despacha ahí.', $municipality));
    }
}
