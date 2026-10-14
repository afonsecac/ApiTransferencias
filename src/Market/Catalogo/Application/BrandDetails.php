<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\Seller;

/**
 * Una marca con su vendedor: lo que devuelven los casos de uso.
 */
final readonly class BrandDetails
{
    public function __construct(public Brand $brand, public ?Seller $seller)
    {
    }
}
