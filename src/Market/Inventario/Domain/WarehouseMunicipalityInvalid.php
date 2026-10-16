<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseMunicipalityInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el municipio %d.', $id));
    }

    public static function notSelectable(string $name): self
    {
        return new self(sprintf('El municipio "%s" está inactivo: no se puede elegir para un almacén.', $name));
    }
}
