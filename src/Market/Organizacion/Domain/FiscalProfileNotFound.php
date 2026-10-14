<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class FiscalProfileNotFound extends \DomainException
{
    public static function forCountry(string $countryCode): self
    {
        return new self(sprintf('No existe un registro fiscal para "%s".', $countryCode));
    }
}
