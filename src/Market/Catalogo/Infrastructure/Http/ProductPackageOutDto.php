<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Domain\PackageOffer;

/**
 * Respuesta de los endpoints del paquete de un producto (estructura de presentación, no es de entrada): el paquete del catálogo
 * del comprador, su precio mínimo como texto con dos decimales y si se sigue ofreciendo.
 */
class ProductPackageOutDto
{
    public function __construct(
        public int $packageId,
        public string $price,
        public string $currency,
        public bool $isActive,
    ) {
    }

    public static function from(PackageOffer $offer): self
    {
        return new self($offer->packageId, $offer->price->toString(), $offer->currency, $offer->isActive);
    }
}
