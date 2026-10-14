<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class ListProductImages
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImageRepository $images,
    ) {
    }

    /**
     * @return list<ProductImage> en el orden de la galería (la primera por posición; la referencia lleva su marca)
     *
     * @throws ProductNotFound
     */
    public function __invoke(int $productId): array
    {
        if ($this->products->findById($productId) === null) {
            throw ProductNotFound::forId($productId);
        }

        return $this->images->findByProduct($productId);
    }
}
