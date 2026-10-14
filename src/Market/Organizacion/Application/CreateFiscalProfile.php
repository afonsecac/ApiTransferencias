<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileAlreadyExists;
use App\Market\Organizacion\Domain\FiscalProfileRepository;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;

final class CreateFiscalProfile
{
    public function __construct(private readonly FiscalProfileRepository $profiles)
    {
    }

    /**
     * @param list<FiscalFieldInput> $fields
     * @throws FiscalProfileAlreadyExists
     * @throws InvalidFiscalProfile
     */
    public function __invoke(string $countryCode, string $name, array $fields): FiscalProfile
    {
        if ($this->profiles->findByCountry($countryCode) !== null) {
            throw FiscalProfileAlreadyExists::forCountry($countryCode);
        }

        $profile = new FiscalProfile($countryCode, $name, FiscalFieldMapper::toFields($fields));
        $this->profiles->save($profile);

        return $profile;
    }
}
