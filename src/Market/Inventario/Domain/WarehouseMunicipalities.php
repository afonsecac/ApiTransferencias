<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Puerto síncrono hacia el catálogo de municipios (contexto Geografía): solo lectura. */
interface WarehouseMunicipalities
{
    public function find(int $id): ?WarehouseMunicipality;

    public function findByCode(string $code): ?WarehouseMunicipality;
}
