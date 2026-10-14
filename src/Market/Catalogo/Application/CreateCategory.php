<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryAlreadyExists;
use App\Market\Catalogo\Domain\CategoryDepthExceeded;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\InvalidCategory;
use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\Slug;

final class CreateCategory
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @param string|null $slug por defecto, derivado del nombre en español
     * @throws InvalidCategory
     * @throws InvalidSeller si el identificador no es válido
     * @throws CategoryNotFound si el padre no existe
     * @throws CategoryDepthExceeded si quedaría en un cuarto nivel
     * @throws CategoryAlreadyExists
     */
    public function __invoke(?int $parentId, string $nameEs, ?string $nameEn, ?string $slug = null, int $position = 0): CategoryDetails
    {
        $slugValue = $slug !== null && trim($slug) !== '' ? new Slug(trim($slug)) : Slug::fromText($nameEs);

        if ($parentId !== null && $this->categories->findById($parentId) === null) {
            throw CategoryNotFound::forId($parentId);
        }
        $tree = new CategoryTree($this->categories->all());
        $tree->assertCanAddChildTo($parentId);
        if ($this->categories->findBySlug($slugValue->value()) !== null) {
            throw CategoryAlreadyExists::forSlug($slugValue->value());
        }

        $category = Category::create($slugValue, $parentId, $nameEs, $nameEn, $position);
        $this->categories->save($category);

        return CategoryDetails::of($category, new CategoryTree($this->categories->all()));
    }
}
