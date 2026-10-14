<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * Carga la galería de un producto comprobando antes que el producto existe.
 */
final class ProductGalleries
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImageRepository $images,
        private readonly ImageSettings $settings,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function of(int $productId): ProductGallery
    {
        if ($this->products->findById($productId) === null) {
            throw ProductNotFound::forId($productId);
        }

        return ProductGallery::of($this->images->findByProduct($productId), $this->settings->maxPerProduct());
    }
}
