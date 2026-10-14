<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Largo, ancho y alto en milímetros (COMR-104). El volumen sale de ellas, nunca se introduce a mano.
 */
final readonly class Dimensions
{
    private function __construct(
        public float $lengthMm,
        public float $widthMm,
        public float $heightMm,
    ) {
    }

    /**
     * @throws InvalidProduct si alguna no es positiva
     */
    public static function of(float $lengthMm, float $widthMm, float $heightMm): self
    {
        foreach ([$lengthMm, $widthMm, $heightMm] as $dimension) {
            if (!is_finite($dimension) || $dimension <= 0) {
                throw new InvalidProduct('Las dimensiones deben ser mayores que cero.');
            }
        }

        return new self($lengthMm, $widthMm, $heightMm);
    }

    /** Volumen en cm³ (que es lo mismo que ml): mm³ / 1000. */
    public function volumeCm3(): float
    {
        return round($this->lengthMm * $this->widthMm * $this->heightMm / 1000, 3);
    }
}
