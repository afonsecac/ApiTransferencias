<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidMeasureSettings;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureSettingsLocked;
use App\Market\Catalogo\Domain\MeasureSettingsStore;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * Cambia las unidades base y el divisor del peso volumétrico (COMR-281). Las unidades base se rechazan si hay productos con
 * cantidades guardadas: sus datos se reinterpretarían. El divisor no toca datos guardados y se cambia siempre.
 */
final class ChangeMeasureSettings
{
    public function __construct(
        private readonly MeasureSettings $settings,
        private readonly MeasureSettingsStore $store,
        private readonly ProductRepository $products,
    ) {
    }

    /**
     * @throws InvalidMeasureSettings una unidad que no existe o no es del tipo que le toca, o un divisor no positivo
     * @throws MeasureSettingsLocked cambia una base y ya hay productos con cantidades
     */
    public function __invoke(string $weightBase, string $volumeBase, int $volumetricDivisor): MeasureSettingsDetails
    {
        $weight = $this->unit($weightBase, MeasureKind::WEIGHT, 'peso');
        $volume = $this->unit($volumeBase, MeasureKind::VOLUME, 'volumen');
        if ($volumetricDivisor <= 0) {
            throw new InvalidMeasureSettings('El divisor del peso volumétrico debe ser un entero positivo.');
        }

        $baseChanges = $weight !== $this->settings->weightBase() || $volume !== $this->settings->volumeBase();
        if ($baseChanges && $this->products->hasPhysicalProducts()) {
            throw MeasureSettingsLocked::withProducts();
        }

        $this->store->save($weight, $volume, $volumetricDivisor);

        return new MeasureSettingsDetails($weight, $volume, $volumetricDivisor, $this->products->hasPhysicalProducts());
    }

    private function unit(string $code, MeasureKind $kind, string $what): MeasureUnit
    {
        $unit = MeasureUnit::tryFrom(trim($code));
        if ($unit === null || $unit->kind() !== $kind) {
            throw new InvalidMeasureSettings(sprintf('«%s» no es una unidad de %s.', $code, $what));
        }

        return $unit;
    }
}
