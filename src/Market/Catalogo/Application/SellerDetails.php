<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerCostCenter;
use App\Market\Catalogo\Domain\SellerOrgUnit;

/**
 * Un vendedor con su unidad organizativa y su centro de costo: lo que devuelven los casos de uso. `productCount` (de cualquier
 * estado) y `brandCount` son null cuando la respuesta no los cuenta (alta, edición, activar).
 */
final readonly class SellerDetails
{
    public function __construct(
        public Seller $seller,
        public ?SellerOrgUnit $orgUnit,
        public ?SellerCostCenter $costCenter,
        public ?int $productCount = null,
        public ?int $brandCount = null,
    ) {
    }

    public static function of(Seller $seller, ?SellerOrgUnit $orgUnit, ?int $productCount = null, ?int $brandCount = null): self
    {
        return new self($seller, $orgUnit, $orgUnit?->costCenter($seller->costCenterId()), $productCount, $brandCount);
    }
}
