<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Los criterios para buscar productos; todos opcionales y combinados con «y». Con varias categorías basta con estar en una
 * (así se busca una categoría con sus subcategorías).
 */
final readonly class ProductFilter
{
    /**
     * @param string|null $query texto libre sobre el sku, los nombres y el código de barras
     * @param list<int> $categoryIds vacío = sin filtro de categoría
     * @param \DateTimeImmutable|null $relevantOn solo los productos cuya relevancia está en vigor ese día
     */
    public function __construct(
        public ?string $query = null,
        public ?ProductStatus $status = null,
        public ?int $sellerId = null,
        public ?int $brandId = null,
        public array $categoryIds = [],
        public ?\DateTimeImmutable $relevantOn = null,
    ) {
    }
}
