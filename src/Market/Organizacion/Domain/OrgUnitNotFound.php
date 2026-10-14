<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class OrgUnitNotFound extends \DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('No existe la unidad organizativa %d.', $id));
    }
}
