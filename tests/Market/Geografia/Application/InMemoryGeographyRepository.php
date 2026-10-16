<?php

declare(strict_types=1);

namespace App\Tests\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyRepository;
use App\Market\Geografia\Domain\Municipality;
use App\Market\Geografia\Domain\Province;
use App\Market\Geografia\Domain\SearchText;

final class InMemoryGeographyRepository implements GeographyRepository
{
    /** @var array<int, Province> */
    private array $provinces = [];
    /** @var array<int, Municipality> */
    private array $municipalities = [];
    private int $nextId = 1;

    public function addProvince(Province $province): Province
    {
        $this->assign($province);
        $this->provinces[$province->id()] = $province;

        return $province;
    }

    public function addMunicipality(Municipality $municipality): Municipality
    {
        $this->assign($municipality);
        $this->municipalities[$municipality->id()] = $municipality;

        return $municipality;
    }

    private function assign(object $entity): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $this->nextId++);
    }

    public function findProvince(int $id): ?Province
    {
        return $this->provinces[$id] ?? null;
    }

    public function findMunicipality(int $id): ?Municipality
    {
        return $this->municipalities[$id] ?? null;
    }

    public function findMunicipalityByCode(string $code): ?Municipality
    {
        foreach ($this->municipalities as $m) {
            if ($m->code() === $code) {
                return $m;
            }
        }

        return null;
    }

    public function provinces(): array
    {
        return array_values($this->provinces);
    }

    public function municipalities(?string $query, ?int $provinceId, ?bool $selectable): array
    {
        $needle = $query === null ? '' : SearchText::normalize($query);

        return array_values(array_filter(
            $this->municipalities,
            fn (Municipality $m) => ($needle === '' || str_contains($m->searchName(), $needle))
                && ($provinceId === null || $m->provinceId() === $provinceId)
                && ($selectable === null || ($m->isActive() && $this->provinces[$m->provinceId()]->isActive()) === $selectable),
        ));
    }

    public function municipalityCounts(): array
    {
        $counts = [];
        foreach ($this->municipalities as $m) {
            $counts[$m->provinceId()] = ($counts[$m->provinceId()] ?? 0) + 1;
        }

        return $counts;
    }

    public function save(Province|Municipality $entity): void
    {
    }
}
