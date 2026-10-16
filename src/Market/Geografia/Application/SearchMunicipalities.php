<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyRepository;
use App\Market\Geografia\Domain\Province;

final class SearchMunicipalities
{
    public function __construct(private readonly GeographyRepository $geography)
    {
    }

    /**
     * @param bool|null $selectable true: solo los que se pueden elegir (municipio y provincia activos)
     *
     * @return list<MunicipalityDetails>
     */
    public function __invoke(?string $query, ?int $provinceId, ?bool $selectable): array
    {
        /** @var array<int, Province> $provinces */
        $provinces = [];
        foreach ($this->geography->provinces() as $province) {
            $provinces[(int) $province->id()] = $province;
        }

        return array_map(
            static fn ($m) => new MunicipalityDetails($m, $provinces[$m->provinceId()]),
            $this->geography->municipalities($query, $provinceId, $selectable),
        );
    }
}
