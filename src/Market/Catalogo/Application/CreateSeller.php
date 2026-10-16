<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerAlreadyExists;
use App\Market\Catalogo\Domain\SellerOrgUnit;
use App\Market\Catalogo\Domain\SellerOrgUnitInvalid;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Shared\Domain\Slug;

final class CreateSeller
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
    ) {
    }

    /**
     * @param string|null $slug por defecto, derivado del nombre (con -2, -3… si ya está en uso)
     * @param int|null $costCenterId por defecto, el principal de la unidad
     * @throws InvalidSeller
     * @throws SellerOrgUnitInvalid
     * @throws SellerAlreadyExists
     */
    public function __invoke(int $orgUnitId, string $name, ?string $slug = null, ?int $costCenterId = null): SellerDetails
    {
        $slugValue = $slug !== null && trim($slug) !== '' ? new Slug(trim($slug)) : Slug::free($name, fn (string $candidate): bool => $this->sellers->findBySlug($candidate) !== null);

        $unit = $this->orgUnits->find($orgUnitId) ?? throw SellerOrgUnitInvalid::unknownUnit($orgUnitId);
        if (!$unit->isSeller()) {
            throw SellerOrgUnitInvalid::notASeller($unit->name);
        }
        $center = $costCenterId === null
            ? $unit->principalCostCenter()
            : $unit->activeCostCenter($costCenterId);
        if ($center === null) {
            throw SellerOrgUnitInvalid::costCenter($costCenterId ?? 0, $unit->name);
        }

        if ($this->sellers->findBySlug($slugValue->value()) !== null) {
            throw SellerAlreadyExists::forSlug($slugValue->value());
        }
        if ($this->sellers->findByOrgUnit($orgUnitId) !== null) {
            throw SellerAlreadyExists::forOrgUnit($orgUnitId);
        }

        $seller = Seller::create($orgUnitId, $center->id, $slugValue, $name);
        $this->sellers->save($seller);

        return SellerDetails::of($seller, $unit);
    }
}
