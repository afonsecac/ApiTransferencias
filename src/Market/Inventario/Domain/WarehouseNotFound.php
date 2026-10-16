<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseNotFound extends \DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('No existe el almacén %d.', $id));
    }
}
