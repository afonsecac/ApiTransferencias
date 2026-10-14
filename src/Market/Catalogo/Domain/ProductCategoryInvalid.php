<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Una categoría elegida para el producto no existe o está desactivada.
 */
final class ProductCategoryInvalid extends \DomainException
{
    public static function unknown(int $categoryId): self
    {
        return new self(sprintf('La categoría %d no existe.', $categoryId));
    }

    public static function inactive(string $name): self
    {
        return new self(sprintf('La categoría "%s" está desactivada.', $name));
    }
}
