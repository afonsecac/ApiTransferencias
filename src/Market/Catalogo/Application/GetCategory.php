<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;

final class GetCategory
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @throws CategoryNotFound
     */
    public function __invoke(int $id): CategoryDetails
    {
        $category = $this->categories->findById($id) ?? throw CategoryNotFound::forId($id);

        return CategoryDetails::of($category, new CategoryTree($this->categories->all()));
    }
}
