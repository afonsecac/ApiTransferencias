<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductBrandInvalid;
use App\Market\Catalogo\Domain\ProductCategoryInvalid;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class UpdateProduct
{
    private readonly ProductReferences $references;
    private readonly ProductDetailsAssembler $assembler;

    public function __construct(
        private readonly ProductRepository $products,
        SellerRepository $sellers,
        BrandRepository $brands,
        CategoryRepository $categories,
        ?ProductImageRepository $images = null,
        private readonly ?MeasureSettings $measures = null,
    ) {
        $this->references = new ProductReferences($brands, $categories);
        $this->assembler = new ProductDetailsAssembler($sellers, $brands, $categories, $images);
    }

    /**
     * El sku y el vendedor no cambian. La marca y las categorías se reemplazan por las que se dan (sin marca, la quita).
     *
     * @throws ProductNotFound
     * @throws InvalidProduct
     * @throws ProductBrandInvalid
     * @throws ProductCategoryInvalid
     */
    public function __invoke(int $id, ProductSpecInput $input): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);
        $spec = $input->toSpec($this->measures === null ? null : MeasureSystem::fromSettings($this->measures));
        $this->references->check($product->sellerId(), $input->brandId, $input->categoryIds, $product->brandId(), $product->categoryIds());

        $product->update($spec);
        $product->classify($input->brandId, $input->categoryIds);
        $this->products->save($product);

        return $this->assembler->one($product);
    }
}
