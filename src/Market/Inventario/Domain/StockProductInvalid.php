<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class StockProductInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el producto %d.', $id));
    }

    public static function digital(string $name): self
    {
        return new self(sprintf('El producto "%s" es digital: no tiene existencias físicas.', $name));
    }

    public static function comboNotAvailable(): self
    {
        return new self('El stock de combos llega con la historia de combos.');
    }
}
