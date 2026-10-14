<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\ProductBrandInvalid;
use App\Market\Catalogo\Domain\ProductCategoryInvalid;

/**
 * Comprueba la marca y las categorías que se le dan a un producto: tienen que existir y estar activas, y la marca ser de
 * su vendedor. Lo que el producto ya tenía no se vuelve a exigir activo: una marca o categoría desactivada después no
 * impide seguir editando el producto.
 */
final class ProductReferences
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * @param list<int> $categoryIds
     * @param list<int> $currentCategoryIds las que el producto ya tiene
     *
     * @throws ProductBrandInvalid
     * @throws ProductCategoryInvalid
     */
    public function check(int $sellerId, ?int $brandId, array $categoryIds, ?int $currentBrandId = null, array $currentCategoryIds = []): void
    {
        if ($brandId !== null) {
            $brand = $this->brands->findById($brandId) ?? throw ProductBrandInvalid::unknown($brandId);
            if ($brand->sellerId() !== $sellerId) {
                throw ProductBrandInvalid::sellerMismatch($brand->name());
            }
            if (!$brand->isActive() && $brandId !== $currentBrandId) {
                throw ProductBrandInvalid::inactive($brand->name());
            }
        }

        foreach (array_unique($categoryIds) as $categoryId) {
            $category = $this->categories->findById($categoryId) ?? throw ProductCategoryInvalid::unknown($categoryId);
            if (!$category->isActive() && !in_array($categoryId, $currentCategoryIds, true)) {
                throw ProductCategoryInvalid::inactive($category->nameEs());
            }
        }
    }
}
