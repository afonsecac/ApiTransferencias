<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseOwnerInvalid extends \DomainException
{
    public static function unknown(int $orgUnitId): self
    {
        return new self(sprintf('No existe la unidad organizativa %d.', $orgUnitId));
    }

    public static function notAllowed(string $name): self
    {
        return new self(sprintf('La unidad "%s" no puede ser dueña de un almacén: debe ser la plataforma o un socio de almacén.', $name));
    }

    public static function costCenter(int $costCenterId, string $name): self
    {
        return new self(sprintf('El centro de costo %d no es un centro activo de la unidad "%s".', $costCenterId, $name));
    }
}
