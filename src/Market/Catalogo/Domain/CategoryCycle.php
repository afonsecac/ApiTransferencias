<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Una categoría no puede colgar de sí misma ni de una de sus descendientes.
 */
final class CategoryCycle extends \DomainException
{
    public static function between(int $id, int $newParentId): self
    {
        return new self(sprintf('La categoría %d no puede colgar de la %d: es ella misma o una de sus descendientes.', $id, $newParentId));
    }
}
