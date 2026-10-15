<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryProductCounts;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\ProductRepository;

final class ListCategories
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly ?ProductRepository $products = null,
    ) {
    }

    /**
     * Las categorías en el orden del árbol (cada una con sus hijas debajo, por posición y nombre), con su nivel.
     *
     * @param string|null $query texto libre sobre los nombres en los dos idiomas y el identificador
     * @return list<CategoryDetails>
     */
    public function __invoke(?string $query, ?bool $active): array
    {
        $tree = new CategoryTree($this->categories->all());
        $counts = $this->products === null ? null : new CategoryProductCounts($this->products->productIdsByCategory(), $tree);
        $details = [];
        $this->walk($tree, null, $details, $query, $active, $counts);

        return $details;
    }

    /**
     * @param list<CategoryDetails> $details
     */
    private function walk(CategoryTree $tree, ?int $parentId, array &$details, ?string $query, ?bool $active, ?CategoryProductCounts $counts): void
    {
        foreach ($tree->childrenOf($parentId) as $category) {
            if ($this->matches($category, $query, $active)) {
                $details[] = CategoryDetails::of($category, $tree, $counts);
            }
            $this->walk($tree, (int) $category->id(), $details, $query, $active, $counts);
        }
    }

    private function matches(Category $category, ?string $query, ?bool $active): bool
    {
        if ($active !== null && $category->isActive() !== $active) {
            return false;
        }
        if ($query === null || $query === '') {
            return true;
        }
        foreach ([$category->nameEs(), $category->nameEn() ?? '', $category->slug()->value()] as $text) {
            if (mb_stripos($text, $query) !== false) {
                return true;
            }
        }

        return false;
    }
}
