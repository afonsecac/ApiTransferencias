<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * El sku y el vendedor no cambian.
     *
     * @throws ProductNotFound
     * @throws InvalidProduct
     */
    public function __invoke(int $id, ProductSpecInput $input): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);
        $product->update($input->toSpec());
        $this->products->save($product);

        return new ProductDetails($product, $this->sellers->findById($product->sellerId()));
    }
}
