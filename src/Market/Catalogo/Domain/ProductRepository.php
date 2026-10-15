<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface ProductRepository
{
    public function findById(int $id): ?Product;

    /**
     * @param string $sku ya normalizado (mayúsculas)
     */
    public function findBySku(string $sku): ?Product;

    /**
     * @return list<Product> por sku
     */
    public function search(ProductFilter $filter, ?int $limit = null, int $offset = 0): array;

    /** Cuántos productos cumplen el filtro (sin paginar). */
    public function count(ProductFilter $filter): int;

    /**
     * @param list<int> $ids
     *
     * @return list<Product> los que existen, por id
     */
    public function findByIds(array $ids): array;

    /**
     * Los sku (en mayúsculas) que empiezan por ese prefijo, para proponer el siguiente consecutivo.
     *
     * @return list<string>
     */
    public function skusStartingWith(string $prefix): array;

    public function save(Product $product): void;
}
