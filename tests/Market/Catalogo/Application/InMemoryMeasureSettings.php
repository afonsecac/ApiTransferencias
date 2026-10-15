<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSettingsStore;
use App\Market\Catalogo\Domain\MeasureUnit;

final class InMemoryMeasureSettings implements MeasureSettings, MeasureSettingsStore
{
    public int $saves = 0;

    public function __construct(
        private MeasureUnit $weightBase = MeasureUnit::KG,
        private MeasureUnit $volumeBase = MeasureUnit::L,
        private int $volumetricDivisor = 6000,
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

    public function save(MeasureUnit $weightBase, MeasureUnit $volumeBase, int $volumetricDivisor): void
    {
        $this->weightBase = $weightBase;
        $this->volumeBase = $volumeBase;
        $this->volumetricDivisor = $volumetricDivisor;
        ++$this->saves;
    }
}
