<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La galería del producto ya tiene el máximo de imágenes permitido.
 */
final class ProductGalleryFull extends \DomainException
{
    public static function withMax(int $max): self
    {
        return new self(sprintf('La galería ya tiene el máximo de %d imágenes.', $max));
    }
}
