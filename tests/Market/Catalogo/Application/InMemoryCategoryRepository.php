<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;

final class InMemoryCategoryRepository implements CategoryRepository
{
    /** @var array<int, Category> */
    public array $categories = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function all(): array
    {
        return array_values($this->categories);
    }

    public function findById(int $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->slug()->value() === $slug) {
                return $category;
            }
        }

        return null;
    }

    public function save(Category $category): void
    {
        if ($category->id() === null) {
            (new \ReflectionProperty(Category::class, 'id'))->setValue($category, $this->nextId++);
        }
        $this->categories[$category->id()] = $category;
        ++$this->saves;
    }
}
