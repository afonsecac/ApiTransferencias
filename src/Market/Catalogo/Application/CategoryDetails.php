<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryProductCounts;
use App\Market\Catalogo\Domain\CategoryTree;

/**
 * Una categoría con su nivel en el árbol (1 para una raíz) y cuántos hijos directos tiene: lo que devuelven los casos de uso.
 * Los contadores de productos (COMR-278) solo se calculan al listar o leer una categoría; en las demás respuestas son null.
 */
final readonly class CategoryDetails
{
    /**
     * @param int|null $productCount los productos de la categoría misma
     * @param int|null $totalProductCount los de la categoría y los de sus subcategorías, sin repetir
     */
    public function __construct(
        public Category $category,
        public int $depth,
        public int $childCount,
        public ?int $productCount = null,
        public ?int $totalProductCount = null,
    ) {
    }

    public static function of(Category $category, CategoryTree $tree, ?CategoryProductCounts $counts = null): self
    {
        $id = (int) $category->id();

        return new self(
            $category,
            $tree->depthOf($id),
            count($tree->childrenOf($id)),
            $counts?->own($id),
            $counts?->withSubcategories($id),
        );
    }
}
