<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\SellerRepository;

final class ListProducts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * @return list<ProductDetails> por sku
     */
    public function __invoke(?string $query, ?ProductStatus $status, ?int $sellerId): array
    {
        $sellers = [];

        return array_map(function ($product) use (&$sellers): ProductDetails {
            $sellers[$product->sellerId()] ??= $this->sellers->findById($product->sellerId());

            return new ProductDetails($product, $sellers[$product->sellerId()]);
        }, $this->products->search($query, $status, $sellerId));
    }
}
