<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\InvalidFiscalData;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitAlreadyExists;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class UpdateOrgUnit
{
    public function __construct(
        private readonly OrgUnitRepository $units,
        private readonly ValidateFiscalData $validateFiscalData,
    ) {
    }

    /**
     * El país no se cambia: sus datos fiscales dependen de él.
     *
     * @throws OrgUnitNotFound
     * @throws InvalidOrgUnit
     * @throws InvalidFiscalData
     * @throws OrgUnitAlreadyExists
     */
    public function __invoke(int $id, OrgUnitInput $input): OrgUnitDetails
    {
        $unit = $this->units->findById($id) ?? throw OrgUnitNotFound::forId($id);

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
