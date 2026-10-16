<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseAlreadyExists extends \DomainException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Ya existe un almacén con el identificador "%s".', $slug));
    }
}
