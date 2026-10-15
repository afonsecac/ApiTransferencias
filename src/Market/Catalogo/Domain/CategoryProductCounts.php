<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Cuántos productos tiene cada categoría (COMR-278): los suyos y los de ella y sus subcategorías. Un producto que está en una
 * categoría y en una de sus subcategorías cuenta una sola vez en la categoría de arriba.
 */
final class CategoryProductCounts
{
    /**
     * @param array<int, list<int>> $productIdsByCategory ids de los productos de cada categoría
     */
    public function __construct(private readonly array $productIdsByCategory, private readonly CategoryTree $tree)
    {
    }

    /** Los productos de la categoría misma. */
    public function own(int $categoryId): int
    {
        return count(array_unique($this->productIdsByCategory[$categoryId] ?? []));
    }

    /** Los productos de la categoría y de todas sus subcategorías, sin repetir. */
    public function withSubcategories(int $categoryId): int
    {
        $ids = $this->productIdsByCategory[$categoryId] ?? [];
        foreach ($this->tree->descendantsOf($categoryId) as $descendant) {
            array_push($ids, ...($this->productIdsByCategory[$descendant] ?? []));
        }

        return count(array_unique($ids));
    }
}
