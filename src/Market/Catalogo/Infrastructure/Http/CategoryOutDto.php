<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\CategoryDetails;

/**
 * Respuesta de los endpoints de categorías (estructura de presentación, no es de entrada).
 */
class CategoryOutDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public ?int $parentId,
        public string $nameEs,
        public ?string $nameEn,
        public int $position,
        public bool $isActive,
        public int $depth,
        public int $childCount,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(CategoryDetails $details): self
    {
        $category = $details->category;

        return new self(
            (int) $category->id(),
            $category->slug()->value(),
            $category->parentId(),
            $category->nameEs(),
            $category->nameEn(),
            $category->position(),
            $category->isActive(),
            $details->depth,
            $details->childCount,
            $category->createdAt()->format(DATE_ATOM),
            $category->updatedAt()->format(DATE_ATOM),
        );
    }
}
