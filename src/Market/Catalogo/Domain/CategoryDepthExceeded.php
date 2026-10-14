<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El árbol de categorías tiene como mucho tres niveles (COMR-174).
 */
final class CategoryDepthExceeded extends \DomainException
{
    public static function maximum(): self
    {
        return new self(sprintf('Las categorías admiten como mucho %d niveles.', CategoryTree::MAX_DEPTH));
    }
}
