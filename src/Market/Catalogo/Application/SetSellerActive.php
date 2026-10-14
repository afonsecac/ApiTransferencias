<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\PlatformSellerProtected;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class SetSellerActive
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
    ) {
    }

    /**
     * @throws SellerNotFound
     * @throws PlatformSellerProtected
     */
    public function __invoke(int $id, bool $active): SellerDetails
    {
        $seller = $this->sellers->findById($id) ?? throw SellerNotFound::forId($id);
        $active ? $seller->activate() : $seller->deactivate();
        $this->sellers->save($seller);

        return SellerDetails::of($seller, $this->orgUnits->find($seller->orgUnitId()));
    }
}
