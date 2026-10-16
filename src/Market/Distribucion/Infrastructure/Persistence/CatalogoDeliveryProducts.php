<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Persistence;

use App\Market\Catalogo\Application\GetProductDeliveryFacts;
use App\Market\Distribucion\Domain\DeliveryProduct;
use App\Market\Distribucion\Domain\DeliveryProducts;

/**
 * Puerto síncrono explícito de Distribución hacia Catálogo (ADR-001): solo lectura y solo a través de su caso de uso. Pasa las
 * cantidades a enteros en millonésimas (6 decimales exactos) para que la cotización no use coma flotante.
 */
final class CatalogoDeliveryProducts implements DeliveryProducts
{
    public function __construct(private readonly GetProductDeliveryFacts $facts)
    {
    }

    public function find(int $id): ?DeliveryProduct
    {
        $facts = ($this->facts)($id);
        if ($facts === null) {
            return null;
        }

        return new DeliveryProduct(
            $facts->id,
            $facts->name,
            $facts->basis,
            $facts->weightKg === null ? null : (int) round($facts->weightKg * 1_000_000),
            $facts->volumeLiters === null ? null : (int) round($facts->volumeLiters * 1_000_000),
            $facts->volumeCm3 === null ? null : (string) $facts->volumeCm3,
            $facts->volumetricDivisor,
        );
    }
}
