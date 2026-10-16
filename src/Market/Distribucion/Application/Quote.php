<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

/**
 * Una cotización de entrega: el almacén que atiende, la moneda, el desglose por línea, la suma, el límite aplicado y el total.
 * Lleva las tarifas usadas y el id de la cobertura para poder congelarlas en la venta.
 */
final readonly class Quote
{
    /**
     * @param array{perKg: string, perL: string, min: string, max: string|null} $rates
     * @param list<QuotedLine> $lines
     */
    public function __construct(
        public int $municipalityId,
        public int $warehouseId,
        public ?int $coverageId,
        public string $currency,
        public string $rateMode,
        public array $rates,
        public array $lines,
        public string $kgAmount,
        public string $litersAmount,
        public string $subtotal,
        public string $limit,
        public string $total,
    ) {
    }
}
