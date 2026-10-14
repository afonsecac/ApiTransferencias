<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\ProductPage;

/**
 * Respuesta del listado de productos: una página y el total que cumple el filtro.
 */
class ProductPageOutDto
{
    /**
     * @param list<ProductOutDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function from(ProductPage $page, ?string $language = null): self
    {
        return new self(
            array_map(static fn ($details): ProductOutDto => ProductOutDto::from($details, $language), $page->items),
            $page->total,
            $page->page,
            $page->perPage,
        );
    }
}
