<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface ProductImageRepository
{
    /**
     * @return list<ProductImage> por posición
     */
    public function findByProduct(int $productId): array;

    /**
     * @param list<int> $productIds
     *
     * @return array<int, list<ProductImage>> por id de producto, cada lista por posición; solo los que tienen imágenes
     */
    public function findByProducts(array $productIds): array;

    public function save(ProductImage $image): void;

    /**
     * Guarda varias imágenes de una vez (un reordenamiento cambia las posiciones de todas).
     *
     * @param list<ProductImage> $images
     */
    public function saveAll(array $images): void;

    public function delete(ProductImage $image): void;
}
