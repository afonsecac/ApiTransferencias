<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

/**
 * Una página de la vista previa del catálogo del comprador y el total que cumple el filtro.
 */
class BuyerCatalogPageOutDto
{
    /**
     * @param list<BuyerCatalogItemOutDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
