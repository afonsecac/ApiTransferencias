<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Puerto síncrono hacia el catálogo de productos (contexto Catálogo): solo lectura. */
interface StockProducts
{
    public function find(int $id): ?StockProduct;

    /**
     * @param list<int> $ids
     *
     * @return array<int, StockProduct> por id; faltan los que no existen
     */
    public function findMany(array $ids): array;
}
