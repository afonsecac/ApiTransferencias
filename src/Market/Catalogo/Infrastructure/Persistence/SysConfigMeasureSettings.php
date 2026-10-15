<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Repository\SysConfigRepository;

/**
 * Las medidas salen de la configuración del sistema: `market.measure.weight_base`, `market.measure.volume_base` y
 * `market.measure.volumetric_divisor`. Las siembra una migración y no tienen valor por defecto: una clave ausente o inválida
 * lanza MeasureSettingsMisconfigured, porque suponer una base distinta de la de los datos guardados los corrompería en silencio.
 */
final class SysConfigMeasureSettings implements MeasureSettings
{
    public const WEIGHT_BASE_KEY = 'market.measure.weight_base';
    public const VOLUME_BASE_KEY = 'market.measure.volume_base';
    public const DIVISOR_KEY = 'market.measure.volumetric_divisor';

    public function __construct(private readonly SysConfigRepository $config)
    {
    }

    public function weightBase(): MeasureUnit
    {
        return $this->unit(self::WEIGHT_BASE_KEY, MeasureKind::WEIGHT);
    }

    public function volumeBase(): MeasureUnit
    {
        return $this->unit(self::VOLUME_BASE_KEY, MeasureKind::VOLUME);
    }

    public function volumetricDivisor(): int
    {
        $value = $this->config->findCachedValue(self::DIVISOR_KEY, true);
        if ($value === null || !ctype_digit(trim($value)) || (int) trim($value) <= 0) {
            throw MeasureSettingsMisconfigured::forKey(self::DIVISOR_KEY, $value, 'un entero positivo');
        }

        return (int) trim($value);
    }

    private function unit(string $key, MeasureKind $kind): MeasureUnit
    {
        $value = $this->config->findCachedValue($key, true);
        $unit = $value === null ? null : MeasureUnit::tryFrom(trim($value));
        if ($unit === null || $unit->kind() !== $kind) {
            throw MeasureSettingsMisconfigured::forKey($key, $value, sprintf('una unidad de %s', $kind === MeasureKind::WEIGHT ? 'peso' : 'volumen'));
        }

        return $unit;
    }
}
