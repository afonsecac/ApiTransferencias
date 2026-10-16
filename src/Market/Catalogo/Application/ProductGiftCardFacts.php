<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

/**
 * Lo que la emisión de gift cards necesita saber de un producto: si es digital con valor, ese valor (fijo o entre un mínimo y un
 * máximo, en unidades menores) y la unidad organizativa de su vendedor, de la que salen los centros de costo.
 */
final readonly class ProductGiftCardFacts
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $digital,
        public bool $fixed,
        public string $currency,
        public int $minMinor,
        public ?int $maxMinor,
        public int $sellerOrgUnitId,
    ) {
    }
}
