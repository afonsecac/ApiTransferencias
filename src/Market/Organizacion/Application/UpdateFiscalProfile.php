<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalFieldCannotBeRemoved;
use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileNotFound;
use App\Market\Organizacion\Domain\FiscalProfileRepository;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;

final class UpdateFiscalProfile
{
    public function __construct(private readonly FiscalProfileRepository $profiles)
    {
    }

    /**
     * @param list<FiscalFieldInput> $fields lista COMPLETA de campos: uno que ya existía no puede faltar
     * @throws FiscalProfileNotFound
     * @throws FiscalFieldCannotBeRemoved
     * @throws InvalidFiscalProfile
     */
    public function __invoke(string $countryCode, string $name, array $fields): FiscalProfile
    {
        $profile = $this->profiles->findByCountry($countryCode) ?? throw FiscalProfileNotFound::forCountry($countryCode);

        // Primero los campos: si fallan, el nombre no se llega a tocar.
        $profile->replaceFields(FiscalFieldMapper::toFields($fields));
        $profile->rename($name);
        $this->profiles->save($profile);

        return $profile;
    }
}
