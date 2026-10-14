<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La unidad organizativa o el centro de costo elegidos no sirven para un vendedor.
 */
final class SellerOrgUnitInvalid extends \DomainException
{
    public static function unknownUnit(int $orgUnitId): self
    {
        return new self(sprintf('La unidad organizativa %d no existe.', $orgUnitId));
    }

    public static function notASeller(string $name): self
    {
        return new self(sprintf('La unidad "%s" no tiene el tipo vendedor.', $name));
    }

    public static function costCenter(int $costCenterId, string $unitName): self
    {
        return new self(sprintf('El centro de costo %d no es un centro activo de la unidad "%s".', $costCenterId, $unitName));
    }
}
