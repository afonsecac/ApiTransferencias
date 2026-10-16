<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Puerto síncrono hacia el catálogo de productos (contexto Catálogo): solo lectura. */
interface DeliveryProducts
{
    public function find(int $id): ?DeliveryProduct;
}
