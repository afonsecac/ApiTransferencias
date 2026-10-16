<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyNotFound;
use App\Market\Geografia\Domain\GeographyRepository;

final class SetProvinceActive
{
    public function __construct(private readonly GeographyRepository $geography)
    {
    }

    /**
     * Desactivarla oculta sus municipios sin cambiar el estado de cada uno: al reactivarla vuelven como estaban.
     *
     * @throws GeographyNotFound
     */
    public function __invoke(int $id, bool $active): ProvinceDetails
    {
        $province = $this->geography->findProvince($id) ?? throw GeographyNotFound::province($id);
        $active ? $province->activate() : $province->deactivate();
        $this->geography->save($province);

        return new ProvinceDetails($province, $this->geography->municipalityCounts()[$id] ?? 0);
    }
}
