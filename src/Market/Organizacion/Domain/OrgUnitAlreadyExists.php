<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class OrgUnitAlreadyExists extends \DomainException
{
    public static function forTaxId(string $countryCode, string $taxId): self
    {
        return new self(sprintf('Ya existe una unidad organizativa con el identificador fiscal "%s" en %s.', $taxId, $countryCode));
    }
}
