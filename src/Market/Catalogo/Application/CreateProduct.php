<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductAlreadyExists;
use App\Market\Catalogo\Domain\ProductBrandInvalid;
use App\Market\Catalogo\Domain\ProductCategoryInvalid;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductSellerInvalid;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Domain\Sku;

final class CreateProduct
{
    private readonly ProductReferences $references;
    private readonly ProductDetailsAssembler $assembler;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
        BrandRepository $brands,
        CategoryRepository $categories,
        ?ProductImageRepository $images = null,
        private readonly ?MeasureSettings $measures = null,
    ) {
        $this->references = new ProductReferences($brands, $categories);
        $this->assembler = new ProductDetailsAssembler($sellers, $brands, $categories, $images);
    }

    /**
     * El producto nace en borrador. Sin vendedor, usa el de plataforma.
     *
     * @throws InvalidProduct
     * @throws ProductSellerInvalid
     * @throws ProductAlreadyExists
     * @throws ProductBrandInvalid la marca no existe, está desactivada o es de otro vendedor
     * @throws ProductCategoryInvalid
     * @throws SellerNotFound si no se da vendedor y no existe el de plataforma
     */
    public function __invoke(string $sku, ?int $sellerId, ProductSpecInput $input): ProductDetails
    {
        $sku = new Sku($sku);
        $spec = $input->toSpec($this->measures === null ? null : MeasureSystem::fromSettings($this->measures));
        $seller = $this->seller($sellerId);

        if ($this->products->findBySku($sku->value()) !== null) {
            throw ProductAlreadyExists::forSku($sku->value());
        }

        $this->references->check((int) $seller->id(), $input->brandId, $input->categoryIds);

        $product = Product::create($sku, (int) $seller->id(), $spec, $input->brandId, $input->categoryIds);
        $this->products->save($product);

        return $this->assembler->one($product);
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
