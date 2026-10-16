<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Una carga con la tarifa aplicada y su importe ya redondeado a 2 decimales (HALF_UP). */
final readonly class PricedLine
{
    public function __construct(public BillableLoad $load, public DeliveryAmount $rate, public int $cents)
    {
    }

    public function amount(): string
    {
        return DeliveryPricing::money($this->cents);
    }
}
