<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/**
 * Un producto tal como lo ve el stock: el vendedor es el propietario de la mercancía y su centro de costo el del propietario;
 * `physical` es falso en un producto digital, que no tiene existencias.
 */
final readonly class StockProduct
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
