<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

/**
 * Lo que el stock necesita saber de un producto: el vendedor es el propietario de la mercancía y su centro de costo es el del
 * propietario; `physical` es falso en un producto digital, que no tiene existencias.
 */
final readonly class ProductStockFacts
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public int $sellerId,
        public string $sellerName,
        public int $sellerCostCenterId,
        public bool $physical,
    ) {
    }
}
