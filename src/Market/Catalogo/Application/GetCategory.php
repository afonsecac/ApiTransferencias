<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryProductCounts;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\ProductRepository;

final class GetCategory
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly ?ProductRepository $products = null,
    ) {
    }

    /**
     * @throws CategoryNotFound
     */
    public function __invoke(int $id): CategoryDetails
    {
        $category = $this->categories->findById($id) ?? throw CategoryNotFound::forId($id);

        $tree = new CategoryTree($this->categories->all());

        return CategoryDetails::of($category, $tree, $this->products === null ? null : new CategoryProductCounts($this->products->productIdsByCategory(), $tree));
    }
}
