<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class CategoryAlreadyExists extends \DomainException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Ya existe una categoría con el identificador "%s".', $slug));
    }
}
