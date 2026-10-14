<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\ImageStore;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class DeleteProductImage
{
    private readonly ProductGalleries $galleries;

    public function __construct(
        ProductRepository $products,
        private readonly ProductImageRepository $images,
        private readonly ImageStore $store,
        ImageSettings $settings,
    ) {
        $this->galleries = new ProductGalleries($products, $images, $settings);
    }

    /**
     * Quita la imagen de la galería (las demás suben de posición y, si era la referencia, la primera pasa a serlo) y
     * borra sus archivos. Los archivos se borran al final: si algo falla antes, no se pierde ninguna imagen.
     *
     * @throws ProductNotFound
     * @throws ProductImageNotFound
     */
    public function __invoke(int $productId, int $imageId): void
    {
        $gallery = $this->galleries->of($productId);
        $removed = $gallery->remove($imageId);
        $this->images->delete($removed);
        $this->images->saveAll($gallery->images());
        $this->store->delete($removed->file());
    }
}
