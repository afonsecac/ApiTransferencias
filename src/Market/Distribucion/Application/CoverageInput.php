<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryAmount;
use App\Market\Distribucion\Domain\DeliveryRates;

final readonly class CoverageInput
{
    public function __construct(
        public string $ratePerKg,
        public string $ratePerL,
        public string $minPrice,
        public ?string $maxPrice,
        public int $priority,
        public bool $confirmZeroRate = false,
        public bool $confirmNoMax = false,
    ) {
    }

    /** @throws \App\Market\Distribucion\Domain\InvalidCoverage */
    public function rates(): DeliveryRates
    {
        return new DeliveryRates(
            DeliveryAmount::fromString($this->ratePerKg),
            DeliveryAmount::fromString($this->ratePerL),
            DeliveryAmount::fromString($this->minPrice),
            $this->maxPrice === null || trim($this->maxPrice) === '' ? null : DeliveryAmount::fromString($this->maxPrice),
        );
    }
}
