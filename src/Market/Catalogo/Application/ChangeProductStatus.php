<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\SellerRepository;

final class ChangeProductStatus
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * Publicar (desde borrador o retirado) o retirar (solo un publicado). Nunca se vuelve a borrador.
     *
     * @throws ProductNotFound
     * @throws InvalidProductTransition
     */
    public function __invoke(int $id, ProductStatus $status): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);
        match ($status) {
            ProductStatus::PUBLISHED => $product->publish(),
            ProductStatus::WITHDRAWN => $product->withdraw(),
            ProductStatus::DRAFT => throw InvalidProductTransition::from($product->status(), $status),
        };
        $this->products->save($product);

        return new ProductDetails($product, $this->sellers->findById($product->sellerId()));
    }
}
