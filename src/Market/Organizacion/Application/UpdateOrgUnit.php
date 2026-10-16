<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidFiscalData;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitAlreadyExists;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;
use App\Market\Organizacion\Domain\OrgUnitTypeInUse;
use App\Market\Organizacion\Domain\OrgUnitUsage;

final class UpdateOrgUnit
{
    public function __construct(
        private readonly OrgUnitRepository $units,
        private readonly ValidateFiscalData $validateFiscalData,
        private readonly OrgUnitUsage $usage,
    ) {
    }

    /**
     * El país no se cambia: sus datos fiscales dependen de él.
     *
     * @throws OrgUnitNotFound
     * @throws InvalidOrgUnit
     * @throws InvalidFiscalData
     * @throws OrgUnitAlreadyExists
     * @throws OrgUnitTypeInUse si deja sin tipo válido a una unidad que es dueña de almacenes
     */
    public function __invoke(int $id, OrgUnitInput $input): OrgUnitDetails
    {
        $unit = $this->units->findById($id) ?? throw OrgUnitNotFound::forId($id);

        // Un almacén exige un dueño que sea la plataforma o un socio de almacén: no se deja inválido en silencio.
        $newTypes = OrgUnitTypes::fromValues($input->types);
        if (array_filter($newTypes, static fn (OrgUnitType $t) => in_array($t, [OrgUnitType::PLATFORM, OrgUnitType::WAREHOUSE_PARTNER], true)) === []) {
            $warehouses = $this->usage->warehouseCount((int) $unit->id());
            if ($warehouses > 0) {
                throw OrgUnitTypeInUse::warehouses($warehouses);
            }
        }

        $unit->update(
            $input->name,
            $input->legalName,
            $input->taxId,
            $input->address,
            $input->billingEmail,
            OrgUnitTypes::fromValues($input->types),
            $input->fiscalData,
        );

        $errors = ($this->validateFiscalData)($unit->countryCode(), $unit->fiscalData());
        if ($errors !== []) {
            throw new InvalidFiscalData($errors);
        }
        $same = $this->units->findByTaxId($unit->countryCode(), $unit->taxId());
        if ($same !== null && $same->id() !== $unit->id()) {
            throw OrgUnitAlreadyExists::forTaxId($unit->countryCode(), $unit->taxId());
        }

        $this->units->save($unit);

        return new OrgUnitDetails($unit, $this->units->costCentersOf($unit));
    }
}
