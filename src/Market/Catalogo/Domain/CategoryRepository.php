<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface CategoryRepository
{
    /**
     * Todas las categorías: son pocas y el árbol (profundidad, ciclos, orden) se calcula sobre ellas.
     *
     * @return list<Category>
     */
    public function all(): array;

    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function save(Category $category): void;
}
