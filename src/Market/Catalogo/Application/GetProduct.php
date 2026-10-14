<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class GetProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(int $id): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);

        return new ProductDetails($product, $this->sellers->findById($product->sellerId()));
    }
}
