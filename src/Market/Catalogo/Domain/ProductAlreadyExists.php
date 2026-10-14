<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class ProductAlreadyExists extends \DomainException
{
    public static function forSku(string $sku): self
    {
        return new self(sprintf('Ya existe un producto con el sku "%s".', $sku));
    }
}
