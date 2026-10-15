<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureUnit;

final class FakeMeasureSettings implements MeasureSettings
{
    public function __construct(
        private readonly MeasureUnit $weightBase = MeasureUnit::KG,
        private readonly MeasureUnit $volumeBase = MeasureUnit::L,
        private readonly int $volumetricDivisor = 6000,
    ) {
    }

    public function weightBase(): MeasureUnit
    {
        return $this->weightBase;
    }

    public function volumeBase(): MeasureUnit
    {
        return $this->volumeBase;
    }

    public function volumetricDivisor(): int
    {
        return $this->volumetricDivisor;
    }
}
