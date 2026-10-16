<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyNotFound;
use App\Market\Geografia\Domain\GeographyRepository;

final class SetMunicipalityActive
{
    public function __construct(private readonly GeographyRepository $geography)
    {
    }

    /**
     * @throws GeographyNotFound
     */
    public function __invoke(int $id, bool $active): MunicipalityDetails
    {
        $municipality = $this->geography->findMunicipality($id) ?? throw GeographyNotFound::municipality($id);
        $active ? $municipality->activate() : $municipality->deactivate();
        $this->geography->save($municipality);
        $province = $this->geography->findProvince($municipality->provinceId()) ?? throw GeographyNotFound::province($municipality->provinceId());

        return new MunicipalityDetails($municipality, $province);
    }
}
