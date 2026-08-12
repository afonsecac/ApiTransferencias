<?php

namespace App\Service\Pricing;

/**
 * Resultado de PackageSalePriceResolver::resolve() — el importe/moneda que
 * se muestra en el listado Y se cobra al comprar (misma resolución para
 * ambos, ver PackageSalePriceResolver). Análogo a
 * App\Service\Provider\ResolvedProductPrice, pero a nivel de paquete de
 * cliente en vez de costo mayorista de producto.
 */
final readonly class ResolvedSalePrice
{
    public function __construct(
        public float $amount,
        public string $currency,
        public PriceSourceEnum $source,
        public ?int $contractId = null,
        public ?float $conversionRate = null,
        public ?\DateTimeImmutable $conversionRateDate = null,
        public ?string $note = null,
    ) {
    }
}
