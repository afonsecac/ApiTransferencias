<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La marca elegida para el producto no sirve: no existe, está desactivada o es de otro vendedor. `reason()` es el código
 * que ve quien llama a la API.
 */
final class ProductBrandInvalid extends \DomainException
{
    public const SELLER_MISMATCH = 'PRODUCT_BRAND_SELLER_MISMATCH';
    public const INVALID = 'PRODUCT_BRAND_INVALID';

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function unknown(int $brandId): self
    {
        return new self(sprintf('La marca %d no existe.', $brandId), self::INVALID);
    }

    public static function inactive(string $name): self
    {
        return new self(sprintf('La marca "%s" está desactivada.', $name), self::INVALID);
    }

    public static function sellerMismatch(string $brand): self
    {
        return new self(sprintf('La marca "%s" no es del vendedor del producto.', $brand), self::SELLER_MISMATCH);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
