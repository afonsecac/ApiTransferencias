<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class SellerAlreadyExists extends \DomainException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Ya existe un vendedor con el identificador "%s".', $slug));
    }

    public static function forOrgUnit(int $orgUnitId): self
    {
        return new self(sprintf('La unidad organizativa %d ya tiene un vendedor.', $orgUnitId));
    }
}
