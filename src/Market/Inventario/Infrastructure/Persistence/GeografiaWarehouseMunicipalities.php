<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Geografia\Application\FindMunicipality;
use App\Market\Geografia\Application\MunicipalityDetails;
use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseMunicipality;

/**
 * Puerto síncrono explícito de Inventario hacia Geografía (ADR-001): solo lectura y solo a través de su caso de uso.
 */
final class GeografiaWarehouseMunicipalities implements WarehouseMunicipalities
{
    public function __construct(private readonly FindMunicipality $findMunicipality)
    {
    }

    public function find(int $id): ?WarehouseMunicipality
    {
        return $this->translate($this->findMunicipality->byId($id));
    }

    public function findByCode(string $code): ?WarehouseMunicipality
    {
        return $this->translate($this->findMunicipality->byCode($code));
    }

    private function translate(?MunicipalityDetails $details): ?WarehouseMunicipality
    {
        return $details === null ? null : new WarehouseMunicipality(
            (int) $details->municipality->id(),
            $details->municipality->code(),
            $details->municipality->name(),
            $details->province->name(),
            $details->selectable,
        );
    }
}
