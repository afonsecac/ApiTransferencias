<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * El desglose de un precio de entrega: el importe de cada línea, la parte por kg y la parte por L, la suma, el límite que se
 * aplicó (`none`, `min` o `max`) y el total. Todo en céntimos enteros.
 */
final readonly class PriceBreakdown
{
    /** @param list<PricedLine> $lines */
    public function __construct(
        public array $lines,
        private int $kgCents,
        private int $litersCents,
        private int $subtotalCents,
        public string $limit,
        private int $totalCents,
    ) {
    }

    public function kgAmount(): string
    {
        return DeliveryPricing::money($this->kgCents);
    }

    public function litersAmount(): string
    {
        return DeliveryPricing::money($this->litersCents);
    }

    public function subtotal(): string
    {
        return DeliveryPricing::money($this->subtotalCents);
    }

    public function total(): string
    {
        return DeliveryPricing::money($this->totalCents);
    }
}
