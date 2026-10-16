<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Puerto síncrono hacia las unidades organizativas (contexto Organización): solo lectura. */
interface WarehouseOwners
{
    public function find(int $orgUnitId): ?WarehouseOwner;
}
