<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryCycle;
use App\Market\Catalogo\Domain\CategoryDepthExceeded;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;

final class MoveCategory
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * Mueve la categoría con todo su subárbol bajo otro padre (o a la raíz).
     *
     * @throws CategoryNotFound
     * @throws CategoryCycle
     * @throws CategoryDepthExceeded
     */
    public function __invoke(int $id, ?int $newParentId): CategoryDetails
    {
        $category = $this->categories->findById($id) ?? throw CategoryNotFound::forId($id);
        if ($newParentId !== null && $this->categories->findById($newParentId) === null) {
            throw CategoryNotFound::forId($newParentId);
        }
        (new CategoryTree($this->categories->all()))->assertCanMove($id, $newParentId);

        $category->moveTo($newParentId);
        $this->categories->save($category);

        return CategoryDetails::of($category, new CategoryTree($this->categories->all()));
    }
}
