<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

interface FiscalProfileRepository
{
    public function findByCountry(string $countryCode): ?FiscalProfile;

    /**
     * @return list<FiscalProfile> ordenados por código de país, con el genérico al final
     */
    public function findAll(): array;

    public function save(FiscalProfile $profile): void;
}
