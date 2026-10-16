<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Persistence;

use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryMunicipality;
use App\Market\Geografia\Application\FindMunicipality;
use App\Market\Geografia\Application\MunicipalityDetails;
use App\Market\Geografia\Application\SearchMunicipalities;

/**
 * Puerto síncrono explícito de Distribución hacia Geografía (ADR-001): solo lectura y solo a través de sus casos de uso.
 */
final class GeografiaDeliveryMunicipalities implements DeliveryMunicipalities
{
    public function __construct(
        private readonly FindMunicipality $findMunicipality,
        private readonly SearchMunicipalities $search,
    ) {
    }

    public function find(int $id): ?DeliveryMunicipality
    {
        $details = $this->findMunicipality->byId($id);

        return $details === null ? null : self::translate($details);
    }

    public function findMany(array $ids): array
    {
        $found = [];
        foreach (array_unique($ids) as $id) {
            $municipality = $this->find($id);
            if ($municipality !== null) {
                $found[$id] = $municipality;
            }
        }

        return $found;
    }

    public function selectableOfProvince(int $provinceId): array
    {
        return array_map(self::translate(...), ($this->search)(null, $provinceId, true));
    }

    private static function translate(MunicipalityDetails $d): DeliveryMunicipality
    {
        return new DeliveryMunicipality((int) $d->municipality->id(), $d->municipality->name(), (int) $d->province->id(), $d->province->name(), $d->selectable);
    }
}
