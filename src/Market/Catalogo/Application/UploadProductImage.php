<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Market\Catalogo\Domain\ImageStore;
use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\ProductGalleryFull;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class UploadProductImage
{
    private readonly ProductGalleries $galleries;

    public function __construct(
        ProductRepository $products,
        private readonly ProductImageRepository $images,
        private readonly ImageStore $store,
        private readonly ImageSettings $settings,
    ) {
        $this->galleries = new ProductGalleries($products, $images, $settings);
    }

    /**
     * Añade una imagen al final de la galería; la primera es la referencia. Primero se comprueba lo barato (producto,
     * peso, sitio en la galería) para no guardar archivos que luego sobran, y si falla el guardado en base se borran.
     *
     * @throws ProductNotFound
     * @throws InvalidProductImage demasiado grande o no es una imagen válida
     * @throws ProductGalleryFull
     */
    public function __invoke(int $productId, string $sourcePath, string $originalName, int $bytes): ProductImage
    {
        $gallery = $this->galleries->of($productId);
        if ($bytes > $this->settings->maxBytes()) {
            throw InvalidProductImage::tooBig($this->settings->maxBytes());
        }
        if ($gallery->count() >= $this->settings->maxPerProduct()) {
            throw ProductGalleryFull::withMax($this->settings->maxPerProduct());
        }

        $stored = $this->store->store($sourcePath);
        try {
            $image = ProductImage::create($productId, $stored->file, $originalName, $stored->mime, $stored->bytes, $stored->width, $stored->height);
            $gallery->add($image);
            $this->images->save($image);
        } catch (\Throwable $e) {
            $this->store->delete($stored->file);

            throw $e;
        }

        return $image;
    }
}
