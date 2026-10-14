<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\InvalidFiscalData;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitAlreadyExists;
use App\Market\Organizacion\Domain\OrgUnitRepository;

final class CreateOrgUnit
{
    public function __construct(
        private readonly OrgUnitRepository $units,
        private readonly ValidateFiscalData $validateFiscalData,
    ) {
    }

    /**
     * @param list<CostCenterInput> $costCenters exactamente uno principal
     * @throws InvalidOrgUnit
     * @throws InvalidFiscalData
     * @throws OrgUnitAlreadyExists
     */
    public function __invoke(string $countryCode, OrgUnitInput $input, array $costCenters): OrgUnit
    {
        $unit = new OrgUnit(
            $input->name,
            $input->legalName,
            $countryCode,
            $input->taxId,
            $input->address,
            $input->billingEmail,
            OrgUnitTypes::fromValues($input->types),
            $input->fiscalData,
            array_map(static fn (CostCenterInput $c) => new CostCenter($c->code, $c->name, $c->principal), $costCenters),
        );

        $errors = ($this->validateFiscalData)($unit->countryCode(), $unit->fiscalData());
        if ($errors !== []) {
            throw new InvalidFiscalData($errors);
        }
        if ($this->units->findByTaxId($unit->countryCode(), $unit->taxId()) !== null) {
            throw OrgUnitAlreadyExists::forTaxId($unit->countryCode(), $unit->taxId());
        }

        $this->units->save($unit);

        return $unit;
    }
}
