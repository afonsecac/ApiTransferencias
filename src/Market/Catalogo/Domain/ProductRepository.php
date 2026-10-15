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
     * Los productos de cada categoría, de cualquier estado: id de categoría → ids de sus productos (solo las que tienen alguno).
     *
     * @return array<int, list<int>>
     */
    public function productIdsByCategory(): array;

    /**
     * Cuántos productos tiene cada marca, de cualquier estado: id de marca → cantidad (solo las que tienen alguno).
     *
     * @return array<int, int>
     */
    public function countsByBrand(): array;

    /**
     * Cuántos productos tiene cada vendedor, de cualquier estado: id de vendedor → cantidad (solo los que tienen alguno).
     *
     * @return array<int, int>
     */
    public function countsBySeller(): array;

    /** Si algún producto tiene cantidades guardadas (contenido de peso, volumen o unidades): los digitales no cuentan. */
    public function hasPhysicalProducts(): bool;

    /**
     * Los sku (en mayúsculas) que empiezan por ese prefijo, para proponer el siguiente consecutivo.
     *
     * @return list<string>
     */
    public function skusStartingWith(string $prefix): array;

    public function save(Product $product): void;
}
