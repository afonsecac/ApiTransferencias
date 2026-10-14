<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El vendedor elegido para el producto no existe o está desactivado.
 */
final class ProductSellerInvalid extends \DomainException
{
    public static function unknown(int $sellerId): self
    {
        return new self(sprintf('El vendedor %d no existe.', $sellerId));
    }

    public static function inactive(string $name): self
    {
        return new self(sprintf('El vendedor "%s" está desactivado.', $name));
    }
}
