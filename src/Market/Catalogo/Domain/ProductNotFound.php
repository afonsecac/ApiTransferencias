<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class ProductNotFound extends \DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('No existe el producto %d.', $id));
    }
}
