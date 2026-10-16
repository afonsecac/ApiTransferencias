<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

/**
 * Un producto tal como lo ve este contexto: digital con un valor fijo (`fixed`) o variable entre `minMinor` y `maxMinor`, y los
 * centros de costo de su vendedor, entre los que se imputa la emisión.
 */
final readonly class GiftCardProduct
{
    /** @param list<GiftCardCostCenter> $costCenters */
    public function __construct(
        public int $id,
        public string $name,
        public bool $digital,
        public bool $fixed,
        public string $currency,
        public int $minMinor,
        public ?int $maxMinor,
        public array $costCenters,
    ) {
    }

    public function principalCostCenter(): ?GiftCardCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->principal && $center->active) {
                return $center;
            }
        }

        return null;
    }

    public function activeCostCenter(int $id): ?GiftCardCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->id === $id && $center->active) {
                return $center;
            }
        }

        return null;
    }
}
