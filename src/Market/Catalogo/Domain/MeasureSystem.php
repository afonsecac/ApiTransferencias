<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Las unidades base en que se guardan las cantidades (COMR-281): una de peso y una de volumen (el conteo es siempre la
 * unidad). Sale de la configuración (MeasureSettings); el estándar de la plataforma son kilogramos y litros.
 */
final readonly class MeasureSystem
{
    /**
     * @throws InvalidProduct si una base no es del tipo que le corresponde
     */
    public function __construct(public MeasureUnit $weightBase, public MeasureUnit $volumeBase)
    {
        if ($weightBase->kind() !== MeasureKind::WEIGHT) {
            throw new InvalidProduct(sprintf('La base de peso "%s" no es una unidad de peso.', $weightBase->value));
        }
        if ($volumeBase->kind() !== MeasureKind::VOLUME) {
            throw new InvalidProduct(sprintf('La base de volumen "%s" no es una unidad de volumen.', $volumeBase->value));
        }
    }

    public static function standard(): self
    {
        return new self(MeasureUnit::KG, MeasureUnit::L);
    }

    public static function fromSettings(MeasureSettings $settings): self
    {
        return new self($settings->weightBase(), $settings->volumeBase());
    }

    public function baseUnit(MeasureKind $kind): MeasureUnit
    {
        return match ($kind) {
            MeasureKind::WEIGHT => $this->weightBase,
            MeasureKind::VOLUME => $this->volumeBase,
            MeasureKind::COUNT => MeasureUnit::UNIT,
        };
    }

    /** Cuántas unidades base vale una de esa unidad. */
    public function factorOf(MeasureUnit $unit): float
    {
        return $unit->factor() / $this->baseUnit($unit->kind())->factor();
    }
}
