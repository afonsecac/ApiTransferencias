<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class SetProductImageReference
{
    private readonly ProductGalleries $galleries;

    public function __construct(ProductRepository $products, private readonly ProductImageRepository $images, ImageSettings $settings)
    {
        $this->galleries = new ProductGalleries($products, $images, $settings);
    }

    /**
     * La imagen de referencia es la de la tarjeta del catálogo; solo una por producto.
     *
     * @return list<ProductImage>
     *
     * @throws ProductNotFound
     * @throws ProductImageNotFound
     */
    public function __invoke(int $productId, int $imageId): array
    {
        $gallery = $this->galleries->of($productId);
        $gallery->setReference($imageId);
        $this->images->saveAll($gallery->images());

        return $gallery->images();
    }
}
