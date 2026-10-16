<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseUserNotFound extends \DomainException
{
    public static function for(int $warehouseId, int $userId): self
    {
        return new self(sprintf('El usuario %d no está vinculado al almacén %d.', $userId, $warehouseId));
    }
}
