<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El paquete con el que un producto se ofrece al comprador (COMR-275): su identificador en el catálogo del comprador, el precio
 * mínimo con el que se publicó y si sigue ofreciéndose. El precio es provisional hasta que existan las tarifas del hito 3.
 */
final readonly class PackageOffer
{
    public function __construct(
        public int $packageId,
        public Amount $price,
        public string $currency,
        public bool $isActive,
    ) {
    }
}
