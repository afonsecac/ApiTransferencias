<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\InvalidCategory;

final class UpdateCategory
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * Cambian los nombres y la posición; el identificador no, y el padre se cambia con MoveCategory.
     *
     * @throws CategoryNotFound
     * @throws InvalidCategory
     */
    public function __invoke(int $id, string $nameEs, ?string $nameEn, int $position): CategoryDetails
    {
        $category = $this->categories->findById($id) ?? throw CategoryNotFound::forId($id);
        $category->update($nameEs, $nameEn, $position);
        $this->categories->save($category);

        return CategoryDetails::of($category, new CategoryTree($this->categories->all()));
    }
}
