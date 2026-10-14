<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\Seller;

/**
 * Un producto con su vendedor: lo que devuelven los casos de uso.
 */
final readonly class ProductDetails
{
    public function __construct(public Product $product, public ?Seller $seller)
    {
    }
}
