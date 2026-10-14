<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class DescribeProductImage
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImageRepository $images,
    ) {
    }

    /**
     * El tipo (frente, etiqueta, pack, ambiente...) y el texto alternativo en español e inglés; en blanco, se borran.
     *
     * @throws ProductNotFound
     * @throws ProductImageNotFound
     * @throws InvalidProductImage
     */
    public function __invoke(int $productId, int $imageId, ?string $kind, ?string $altEs, ?string $altEn): ProductImage
    {
        if ($this->products->findById($productId) === null) {
            throw ProductNotFound::forId($productId);
        }
        $image = ProductGallery::of($this->images->findByProduct($productId), PHP_INT_MAX)->find($imageId);
        $image->describe($kind, $altEs, $altEn);
        $this->images->save($image);

        return $image;
    }
}
