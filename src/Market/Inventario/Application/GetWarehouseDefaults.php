<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseMunicipality;
use App\Market\Inventario\Domain\WarehouseSettings;

final class GetWarehouseDefaults
{
    public function __construct(
        private readonly WarehouseSettings $settings,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /** El municipio que el alta de un almacén nuevo propone (el de Configuración), si existe. */
    public function __invoke(): ?WarehouseMunicipality
    {
        $code = $this->settings->defaultMunicipalityCode();

        return $code === null ? null : $this->municipalities->findByCode($code);
    }
}
