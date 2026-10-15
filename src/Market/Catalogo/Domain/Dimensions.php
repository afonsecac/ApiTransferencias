<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Largo, ancho y alto en milímetros (COMR-104). El volumen sale de ellas, nunca se introduce a mano. `unit` es la unidad en que
 * se escribieron (COMR-281), solo para mostrarlas igual al editar: lo guardado siempre son milímetros.
 */
final readonly class Dimensions
{
    private function __construct(
        public float $lengthMm,
        public float $widthMm,
        public float $heightMm,
        public DimensionUnit $unit = DimensionUnit::MM,
    ) {
    }

    /**
     * @throws InvalidProduct si alguna no es positiva
     */
    public static function of(float $lengthMm, float $widthMm, float $heightMm, DimensionUnit $unit = DimensionUnit::MM): self
    {
        foreach ([$lengthMm, $widthMm, $heightMm] as $dimension) {
            if (!is_finite($dimension) || $dimension <= 0) {
                throw new InvalidProduct('Las dimensiones deben ser mayores que cero.');
            }
        }

        return new self($lengthMm, $widthMm, $heightMm, $unit);
    }

    /** Volumen en cm³ (que es lo mismo que ml): mm³ / 1000. */
    public function volumeCm3(): float
    {
        return round($this->lengthMm * $this->widthMm * $this->heightMm / 1000, 3);
    }

    /** Volumen expresado en la unidad base de volumen del sistema (por defecto litros: mm³ / 1.000.000). */
    public function volume(MeasureSystem $system): float
    {
        return round($this->lengthMm * $this->widthMm * $this->heightMm / 1_000_000 / $system->volumeBase->factor(), 6);
    }
}
