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
     * @param string|null $query texto libre sobre el sku y los nombres del producto
     * @return list<Product> por sku
     */
    public function search(?string $query, ?ProductStatus $status, ?int $sellerId): array;

    public function save(Product $product): void;
}
