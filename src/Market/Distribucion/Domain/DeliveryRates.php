<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * Las tarifas lineales de un municipio de un almacén: por kg, por L, mínimo y máximo por entrega (el máximo vacío es «sin tope»).
 */
final readonly class DeliveryRates
{
    public function __construct(
        public DeliveryAmount $perKg,
        public DeliveryAmount $perL,
        public DeliveryAmount $min,
        public ?DeliveryAmount $max,
    ) {
        if ($max !== null && $min->isGreaterThan($max)) {
            throw new InvalidCoverage('El precio máximo no puede ser menor que el mínimo.');
        }
    }

    public function hasZeroRate(): bool
    {
        return $this->perKg->isZero() || $this->perL->isZero();
    }

    public function hasNoMax(): bool
    {
        return $this->max === null;
    }
}
