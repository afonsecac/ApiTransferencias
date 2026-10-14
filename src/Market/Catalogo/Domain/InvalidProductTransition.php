<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class InvalidProductTransition extends \DomainException
{
    public static function from(ProductStatus $from, ProductStatus $to): self
    {
        return new self(sprintf('Un producto en estado "%s" no puede pasar a "%s".', $from->value, $to->value));
    }
}
