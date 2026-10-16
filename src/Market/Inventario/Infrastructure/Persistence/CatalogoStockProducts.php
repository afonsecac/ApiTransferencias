<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Catalogo\Application\GetProductStockFacts;
use App\Market\Catalogo\Application\ProductStockFacts;
use App\Market\Inventario\Domain\StockProduct;
use App\Market\Inventario\Domain\StockProducts;

/**
 * Puerto síncrono explícito de Inventario hacia Catálogo (ADR-001): solo lectura y solo a través de su caso de uso.
 */
final class CatalogoStockProducts implements StockProducts
{
    public function __construct(private readonly GetProductStockFacts $facts)
    {
    }

    public function find(int $id): ?StockProduct
    {
        $facts = ($this->facts)($id);

        return $facts === null ? null : self::translate($facts);
    }

    public function findMany(array $ids): array
    {
        return array_map(self::translate(...), $this->facts->many($ids));
    }

    private static function translate(ProductStockFacts $f): StockProduct
    {
        return new StockProduct($f->id, $f->sku, $f->name, $f->sellerId, $f->sellerName, $f->sellerCostCenterId, $f->physical);
    }
}
