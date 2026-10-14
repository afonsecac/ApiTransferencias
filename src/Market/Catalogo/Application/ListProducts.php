<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\ProductFilter;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;

final class ListProducts
{
    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDetailsAssembler $assembler,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * Los productos que cumplen los filtros, por sku y paginados. Con categoría, incluye por defecto las de sus
     * subcategorías.
     */
    public function __invoke(
        ?string $query,
        ?ProductStatus $status,
        ?int $sellerId,
        ?int $brandId = null,
        ?int $categoryId = null,
        bool $withSubcategories = true,
        int $page = 1,
        int $perPage = 25,
    ): ProductPage {
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $filter = new ProductFilter($query, $status, $sellerId, $brandId, $this->categoryIds($categoryId, $withSubcategories));

        return new ProductPage(
            $this->assembler->many($this->products->search($filter, $perPage, ($page - 1) * $perPage)),
            $this->products->count($filter),
            $page,
            $perPage,
        );
    }

    /**
     * @return list<int>
     */
    private function categoryIds(?int $categoryId, bool $withSubcategories): array
    {
        if ($categoryId === null) {
            return [];
        }
        if (!$withSubcategories) {
            return [$categoryId];
        }
        $tree = new CategoryTree($this->categories->all());
        $ids = [$categoryId];
        $pending = [$categoryId];
        while ($pending !== []) {
            foreach ($tree->childrenOf(array_shift($pending)) as $child) {
                $ids[] = (int) $child->id();
                $pending[] = (int) $child->id();
            }
        }

        return $ids;
    }
}
