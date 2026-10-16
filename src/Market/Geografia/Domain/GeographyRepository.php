<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

interface GeographyRepository
{
    public function findProvince(int $id): ?Province;

    public function findMunicipality(int $id): ?Municipality;

    public function findMunicipalityByCode(string $code): ?Municipality;

    /** @return list<Province> en orden geográfico estable (por id) */
    public function provinces(): array;

    /**
     * @param bool|null $selectable true: municipio y provincia activos; false: alguno inactivo; null: todos
     *
     * @return list<Municipality> ordenados por nombre
     */
    public function municipalities(?string $query, ?int $provinceId, ?bool $selectable): array;

    /** @return array<int, int> municipios por id de provincia */
    public function municipalityCounts(): array;

    public function save(Province|Municipality $entity): void;
}
