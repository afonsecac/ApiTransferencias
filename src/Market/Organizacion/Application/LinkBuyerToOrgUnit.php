<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\Buyer;
use App\Market\Organizacion\Domain\BuyerNotFound;
use App\Market\Organizacion\Domain\BuyerRegistry;
use App\Market\Organizacion\Domain\CostCenterDraft;
use App\Market\Organizacion\Domain\CostCenterSet;
use App\Market\Organizacion\Domain\CountryCodes;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;
use App\Market\Organizacion\Domain\UnknownBuyerCountry;

/**
 * Deja un comprador (Client) referenciado a una unidad organizativa de tipo comprador, con un centro de costo
 * principal que lleva su nombre (COMR-272). Es idempotente: si el cliente ya tiene unidad la devuelve, y si ya
 * existe una con el mismo identificador fiscal en su país la reutiliza y le añade el tipo comprador.
 *
 * Los datos fiscales propios del país quedan vacíos hasta que alguien los edite: por eso aquí no se validan
 * contra el registro fiscal (lo hará la edición normal de la unidad).
 */
final class LinkBuyerToOrgUnit
{
    public const PLACEHOLDER_ADDRESS = 'Sin dirección registrada';
    public const PRINCIPAL_CODE = 'PRINCIPAL';

    public function __construct(
        private readonly OrgUnitRepository $units,
        private readonly BuyerRegistry $buyers,
    ) {
    }

    /**
     * @throws BuyerNotFound
     * @throws UnknownBuyerCountry
     * @throws InvalidOrgUnit
     */
    public function __invoke(int $clientId): OrgUnitDetails
    {
        $buyer = $this->buyers->find($clientId) ?? throw BuyerNotFound::forId($clientId);

        $linkedId = $this->buyers->orgUnitIdOf($clientId);
        if ($linkedId !== null) {
            $linked = $this->units->findById($linkedId) ?? throw OrgUnitNotFound::forId($linkedId);

            return new OrgUnitDetails($linked, $this->units->costCentersOf($linked));
        }

        $countryCode = CountryCodes::iso2FromIso3($buyer->country);
        $taxId = strtoupper(trim($buyer->identification));

        $existing = $this->units->findByTaxId($countryCode, $taxId);
        $details = $existing !== null
            ? $this->addBuyerType($existing)
            : $this->create($buyer, $countryCode);

        $this->buyers->link($clientId, $details->unit);

        return $details;
    }

    private function create(Buyer $buyer, string $countryCode): OrgUnitDetails
    {
        $unit = new OrgUnit(
            mb_substr($buyer->name, 0, 160),
            mb_substr($buyer->name, 0, 200),
            $countryCode,
            $buyer->identification,
            $this->addressOf($buyer),
            $buyer->email,
            [OrgUnitType::BUYER],
            [],
        );
        $set = CostCenterSet::start($unit, [new CostCenterDraft(self::PRINCIPAL_CODE, mb_substr($buyer->name, 0, 120), true)]);

        $this->units->save($unit, $set->all());

        return new OrgUnitDetails($unit, $set->all());
    }

    private function addBuyerType(OrgUnit $unit): OrgUnitDetails
    {
        if (!in_array(OrgUnitType::BUYER, $unit->types(), true)) {
            $unit->update(
                $unit->name(),
                $unit->legalName(),
                $unit->taxId(),
                $unit->address(),
                $unit->billingEmail(),
                [...$unit->types(), OrgUnitType::BUYER],
                $unit->fiscalData(),
            );
            $this->units->save($unit);
        }

        return new OrgUnitDetails($unit, $this->units->costCentersOf($unit));
    }

    private function addressOf(Buyer $buyer): string
    {
        $address = trim((string) $buyer->address);

        return mb_substr($address !== '' ? $address : self::PLACEHOLDER_ADDRESS, 0, 300);
    }
}
