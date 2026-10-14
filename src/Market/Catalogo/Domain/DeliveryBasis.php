<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Sobre qué se tarifa la entrega del producto (COMR-177): peso o volumen, y en qué unidad se muestra.
 */
final readonly class DeliveryBasis
{
    private function __construct(
        private MeasureKind $kind,
        private MeasureUnit $unit,
    ) {
    }

    /**
     * @throws InvalidProduct si no es peso ni volumen, o la unidad no es de ese tipo
     */
    public static function of(MeasureKind $kind, MeasureUnit $unit): self
    {
        if ($kind === MeasureKind::COUNT) {
            throw new InvalidProduct('La entrega se tarifa por peso o por volumen.');
        }
        if ($unit->kind() !== $kind) {
            throw new InvalidProduct(sprintf('La unidad "%s" no es de %s.', $unit->value, $kind->value));
        }

        return new self($kind, $unit);
    }

    public function kind(): MeasureKind
    {
        return $this->kind;
    }

    public function unit(): MeasureUnit
    {
        return $this->unit;
    }
}
