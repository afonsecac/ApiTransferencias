<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class GetSeller
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
    ) {
    }

    /**
     * @throws SellerNotFound
     */
    public function __invoke(int $id): SellerDetails
    {
        $seller = $this->sellers->findById($id) ?? throw SellerNotFound::forId($id);

        return SellerDetails::of($seller, $this->orgUnits->find($seller->orgUnitId()));
    }
}
