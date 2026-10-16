<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * Lo que la entrega necesita saber de un producto: su base de tarifa (`weight` o `volume`; null si es digital), su peso total en
 * micro-kilogramos y su volumen total en micro-litros (6 decimales, como enteros), el volumen de una unidad en cm³ (para el
 * peso volumétrico) y el divisor volumétrico de la configuración.
 */
final readonly class DeliveryProduct
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $basis,
        public ?int $weightMicroKg,
        public ?int $volumeMicroL,
        public ?string $volumeCm3,
        public int $volumetricDivisor,
    ) {
    }
}
