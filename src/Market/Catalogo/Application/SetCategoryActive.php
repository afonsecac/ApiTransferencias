<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;

final class SetCategoryActive
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * Desactivarla no toca a sus hijas ni la quita de los productos que la usan: solo la oculta de los selectores.
     *
     * @throws CategoryNotFound
     */
    public function __invoke(int $id, bool $active): CategoryDetails
    {
        $category = $this->categories->findById($id) ?? throw CategoryNotFound::forId($id);
        $active ? $category->activate() : $category->deactivate();
        $this->categories->save($category);

        return CategoryDetails::of($category, new CategoryTree($this->categories->all()));
    }
}
