<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\Seller;

/**
 * Un producto con su vendedor, su marca y sus categorías: lo que devuelven los casos de uso.
 */
final readonly class ProductDetails
{
    /**
     * @param list<Category> $categories en el orden del producto (por id)
     * @param array<int, string> $categoryPaths la ruta de cada categoría desde la raíz, como «Alimentos › Arroz»
     */
    public function __construct(
        public Product $product,
        public ?Seller $seller,
        public ?Brand $brand = null,
        public array $categories = [],
        public array $categoryPaths = [],
    )
    {
    }
}
