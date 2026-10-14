<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileRepository;

/**
 * Valida los datos fiscales de una unidad organizativa contra el registro de su país; si el país no tiene
 * uno propio usa el genérico. Es el puerto que usará la historia de unidades organizativas (COMR-271).
 */
final class ValidateFiscalData
{
    public function __construct(private readonly FiscalProfileRepository $profiles)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string> código de error por clave; vacío si los datos son correctos
     */
    public function __invoke(string $countryCode, array $data): array
    {
        $profile = $this->profiles->findByCountry($countryCode)
            ?? $this->profiles->findByCountry(FiscalProfile::GENERIC_CODE)
            ?? FiscalProfile::generic();

        return $profile->validate($data);
    }
}
