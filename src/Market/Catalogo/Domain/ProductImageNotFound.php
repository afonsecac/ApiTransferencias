<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class ProductImageNotFound extends \DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('La imagen %d no existe en la galería de este producto.', $id));
    }
}
