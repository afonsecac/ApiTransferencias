<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileRepository;

final class InMemoryFiscalProfileRepository implements FiscalProfileRepository
{
    /** @var array<string, FiscalProfile> */
    public array $profiles = [];

    public int $saves = 0;

    public function findByCountry(string $countryCode): ?FiscalProfile
    {
        return $this->profiles[$countryCode] ?? null;
    }

    public function findAll(): array
    {
        $all = $this->profiles;
        ksort($all);

        return array_values($all);
    }

    public function save(FiscalProfile $profile): void
    {
        $this->profiles[$profile->countryCode()] = $profile;
        ++$this->saves;
    }
}
