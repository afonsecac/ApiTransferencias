<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileNotFound;
use App\Market\Organizacion\Domain\FiscalProfileRepository;

final class GetFiscalProfile
{
    public function __construct(private readonly FiscalProfileRepository $profiles)
    {
    }

    /**
     * @throws FiscalProfileNotFound
     */
    public function __invoke(string $countryCode): FiscalProfile
    {
        return $this->profiles->findByCountry($countryCode) ?? throw FiscalProfileNotFound::forCountry($countryCode);
    }
}
