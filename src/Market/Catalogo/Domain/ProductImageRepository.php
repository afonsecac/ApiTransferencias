<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface ProductImageRepository
{
    /**
     * @return list<ProductImage> por posición
     */
    public function findByProduct(int $productId): array;

    public function save(ProductImage $image): void;

    /**
     * Guarda varias imágenes de una vez (un reordenamiento cambia las posiciones de todas).
     *
     * @param list<ProductImage> $images
     */
    public function saveAll(array $images): void;

    public function delete(ProductImage $image): void;
}
