<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

/**
 * Lo que la entrega necesita saber de un producto, ya en kilogramos y litros: su base de tarifa (`weight`, `volume` o null si es
 * digital), el peso total en kg, el volumen total en L, el volumen de la caja en cm³ y el divisor volumétrico de la configuración.
 */
final readonly class ProductDeliveryFacts
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $basis,
        public ?float $weightKg,
        public ?float $volumeLiters,
        public ?float $volumeCm3,
        public int $volumetricDivisor,
    ) {
    }
}
