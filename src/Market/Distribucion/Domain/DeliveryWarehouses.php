<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Puerto síncrono hacia los almacenes (contexto Inventario): solo lectura. */
interface DeliveryWarehouses
{
    public function find(int $id): ?DeliveryWarehouse;

    /** @return list<int> los almacenes que pueden vender (activos) */
    public function sellableIds(): array;
}
