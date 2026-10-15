<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\Seller;

/**
 * Una marca con su vendedor: lo que devuelven los casos de uso. `productCount` son los productos de la marca de cualquier
 * estado; es null cuando la respuesta no los cuenta (alta, edición, activar).
 */
final readonly class BrandDetails
{
    public function __construct(public Brand $brand, public ?Seller $seller, public ?int $productCount = null)
    {
    }
}
