<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class StockWarehouseInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el almacén %d.', $id));
    }

    public static function draft(string $name): self
    {
        return new self(sprintf('El almacén "%s" está en borrador: no admite stock ni movimientos hasta que se active.', $name));
    }
}
