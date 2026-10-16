<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyRepository;
use App\Market\Geografia\Domain\Municipality;

/**
 * La consulta de un municipio que Geografía ofrece a los demás contextos (p. ej. Inventario): solo lectura.
 */
final class FindMunicipality
{
    public function __construct(private readonly GeographyRepository $geography)
    {
    }

    public function byId(int $id): ?MunicipalityDetails
    {
        return $this->details($this->geography->findMunicipality($id));
    }

    public function byCode(string $code): ?MunicipalityDetails
    {
        return $this->details($this->geography->findMunicipalityByCode($code));
    }

    private function details(?Municipality $municipality): ?MunicipalityDetails
    {
        $province = $municipality === null ? null : $this->geography->findProvince($municipality->provinceId());

        return $municipality === null || $province === null ? null : new MunicipalityDetails($municipality, $province);
    }
}
