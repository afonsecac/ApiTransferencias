<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\Market\Organizacion\Application\OrgUnitDetails;
use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\CountryCodes;
use App\Market\Organizacion\Domain\OrgUnitType;

/**
 * Respuesta de los endpoints de unidades organizativas (estructura de presentación, no es de entrada).
 */
class OrgUnitOutDto
{
    /**
     * @param list<string> $types
     * @param array<string, mixed> $fiscalData
     * @param list<array<string, mixed>> $costCenters
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $legalName,
        public string $countryCode,
        public ?string $countryCodeIso3,
        public string $taxId,
        public string $address,
        public string $billingEmail,
        public array $types,
        public array $fiscalData,
        public array $costCenters,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(OrgUnitDetails $details): self
    {
        $unit = $details->unit;

        return new self(
            (int) $unit->id(),
            $unit->name(),
            $unit->legalName(),
            $unit->countryCode(),
            CountryCodes::iso3FromIso2($unit->countryCode()),
            $unit->taxId(),
            $unit->address(),
            $unit->billingEmail(),
            array_map(static fn (OrgUnitType $t) => $t->value, $unit->types()),
            $unit->fiscalData(),
            array_map(static fn (CostCenter $c) => [
                'id' => $c->id(),
                'code' => $c->code(),
                'name' => $c->name(),
                'principal' => $c->isPrincipal(),
                'active' => $c->isActive(),
            ], $details->costCenters),
            $unit->createdAt()->format(DATE_ATOM),
            $unit->updatedAt()->format(DATE_ATOM),
        );
    }
}
