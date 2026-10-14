<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductAlreadyExists;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductSellerInvalid;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Domain\Sku;

final class CreateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * El producto nace en borrador. Sin vendedor, usa el de plataforma.
     *
     * @throws InvalidProduct
     * @throws ProductSellerInvalid
     * @throws ProductAlreadyExists
     * @throws SellerNotFound si no se da vendedor y no existe el de plataforma
     */
    public function __invoke(string $sku, ?int $sellerId, ProductSpecInput $input): ProductDetails
    {
        $sku = new Sku($sku);
        $spec = $input->toSpec();
        $seller = $this->seller($sellerId);

        if ($this->products->findBySku($sku->value()) !== null) {
            throw ProductAlreadyExists::forSku($sku->value());
        }

        $product = Product::create($sku, (int) $seller->id(), $spec);
        $this->products->save($product);

        return new ProductDetails($product, $seller);
    }

    private function seller(?int $sellerId): Seller
    {
        $seller = $sellerId === null
            ? ($this->sellers->findPlatform() ?? throw SellerNotFound::platform())
            : ($this->sellers->findById($sellerId) ?? throw ProductSellerInvalid::unknown($sellerId));
        if (!$seller->isActive()) {
            throw ProductSellerInvalid::inactive($seller->name());
        }

        return $seller;
    }
}
