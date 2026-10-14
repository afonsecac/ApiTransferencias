<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\InvalidGalleryOrder;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class ReorderProductImages
{
    private readonly ProductGalleries $galleries;

    public function __construct(ProductRepository $products, private readonly ProductImageRepository $images, ImageSettings $settings)
    {
        $this->galleries = new ProductGalleries($products, $images, $settings);
    }

    /**
     * @param list<int> $ids el orden nuevo: todas las imágenes de la galería, una vez cada una
     *
     * @return list<ProductImage>
     *
     * @throws ProductNotFound
     * @throws InvalidGalleryOrder
     */
    public function __invoke(int $productId, array $ids): array
    {
        $gallery = $this->galleries->of($productId);
        $gallery->reorder($ids);
        $this->images->saveAll($gallery->images());

        return $gallery->images();
    }
}
