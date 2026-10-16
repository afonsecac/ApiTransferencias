<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

interface WarehouseSettings
{
    /** El código del municipio por defecto de los almacenes (`market.warehouse.default_municipality`); null si no está configurado. */
    public function defaultMunicipalityCode(): ?string;
}
