<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class StockLineNotFound extends \DomainException
{
    public static function for(int $warehouseId, int $itemId): self
    {
        return new self(sprintf('El almacén %d no tiene existencias del artículo %d.', $warehouseId, $itemId));
    }
}
