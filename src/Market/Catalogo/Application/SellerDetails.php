<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerCostCenter;
use App\Market\Catalogo\Domain\SellerOrgUnit;

/**
 * Un vendedor con su unidad organizativa y su centro de costo: lo que devuelven los casos de uso.
 */
final readonly class SellerDetails
{
    public function __construct(
        public Seller $seller,
        public ?SellerOrgUnit $orgUnit,
        public ?SellerCostCenter $costCenter,
    ) {
    }

    public static function of(Seller $seller, ?SellerOrgUnit $orgUnit): self
    {
        return new self($seller, $orgUnit, $orgUnit?->costCenter($seller->costCenterId()));
    }
}
