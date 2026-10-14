<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnitInvalid;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class UpdateSeller
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
    ) {
    }

    /**
     * El identificador y la unidad no cambian; sí el nombre y el centro de costo.
     *
     * @param int|null $costCenterId nulo = no cambiarlo
     * @throws SellerNotFound
     * @throws InvalidSeller
     * @throws SellerOrgUnitInvalid
     */
    public function __invoke(int $id, string $name, ?int $costCenterId = null): SellerDetails
    {
        $seller = $this->sellers->findById($id) ?? throw SellerNotFound::forId($id);
        $unit = $this->orgUnits->find($seller->orgUnitId()) ?? throw SellerOrgUnitInvalid::unknownUnit($seller->orgUnitId());

        if ($costCenterId !== null && $costCenterId !== $seller->costCenterId()) {
            if ($unit->activeCostCenter($costCenterId) === null) {
                throw SellerOrgUnitInvalid::costCenter($costCenterId, $unit->name);
            }
            $seller->chargeTo($costCenterId);
        }
        $seller->rename($name);
        $this->sellers->save($seller);

        return SellerDetails::of($seller, $unit);
    }
}
