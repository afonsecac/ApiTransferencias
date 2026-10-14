<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryTree;

/**
 * Una categoría con su nivel en el árbol (1 para una raíz) y cuántos hijos directos tiene: lo que devuelven los casos de uso.
 */
final readonly class CategoryDetails
{
    public function __construct(public Category $category, public int $depth, public int $childCount)
    {
    }

    public static function of(Category $category, CategoryTree $tree): self
    {
        $id = (int) $category->id();

        return new self($category, $tree->depthOf($id), count($tree->childrenOf($id)));
    }
}
