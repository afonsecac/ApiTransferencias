<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

/**
 * Una página de productos y el total que cumple el filtro.
 */
final readonly class ProductPage
{
    /**
     * @param list<ProductDetails> $items
     */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage)
    {
    }
}
