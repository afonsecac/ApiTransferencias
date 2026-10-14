<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Lo que describe a un producto y puede editarse: textos, contenido y unidades, referencias por unidad, dimensiones,
 * base de tarifa, área y código de barras. Reúne las reglas entre sus partes:
 *
 * - Un producto por peso o por volumen lleva su contenido en peso o volumen; uno por unidades (conteo) debe llevar el
 *   peso o el volumen de UNA unidad (basta uno de los dos) y esas referencias solo existen en los de conteo (COMR-141).
 * - La base de tarifa elegida (peso o volumen) tiene que poder calcularse con lo que se sabe del producto (COMR-177).
 * - Las dimensiones son las del producto, o las de una unidad si es por conteo; con ellas el volumen se deriva (COMR-104).
 */
final readonly class ProductSpec
{
    public function __construct(
        public ProductTexts $texts,
        public Quantity $content,
        public ?Quantity $unitWeight,
        public ?Quantity $unitVolume,
        public ?Dimensions $dimensions,
        public DeliveryBasis $deliveryBasis,
        public ?ProductArea $area = null,
        public ?string $barcode = null,
    ) {
        $this->validateReferences();

        if ($deliveryBasis->kind() === MeasureKind::WEIGHT && $this->totalWeightG() === null) {
            throw new InvalidProduct('Para tarifar por peso el producto necesita su peso (o el peso de una unidad).');
        }
        if ($deliveryBasis->kind() === MeasureKind::VOLUME && $this->totalVolumeMl() === null) {
            throw new InvalidProduct('Para tarifar por volumen el producto necesita su volumen, el de una unidad o sus dimensiones.');
        }
        if ($barcode !== null && preg_match('/^[0-9]{8,14}$/', $barcode) !== 1) {
            throw new InvalidProduct('El código de barras son entre 8 y 14 dígitos.');
        }
    }

    /** Peso total en gramos, o null si no se puede saber. */
    public function totalWeightG(): ?float
    {
        return match ($this->content->kind()) {
            MeasureKind::WEIGHT => $this->content->baseValue(),
            MeasureKind::COUNT => $this->unitWeight === null ? null : round($this->content->baseValue() * $this->unitWeight->baseValue(), 3),
            MeasureKind::VOLUME => null,
        };
    }

    /** Volumen total en mililitros (= cm³), o null si no se puede saber. */
    public function totalVolumeMl(): ?float
    {
        return match ($this->content->kind()) {
            MeasureKind::VOLUME => $this->content->baseValue(),
            MeasureKind::COUNT => match (true) {
                $this->unitVolume !== null => round($this->content->baseValue() * $this->unitVolume->baseValue(), 3),
                $this->dimensions !== null => round($this->content->baseValue() * $this->dimensions->volumeCm3(), 3),
                default => null,
            },
            MeasureKind::WEIGHT => $this->dimensions?->volumeCm3(),
        };
    }

    private function validateReferences(): void
    {
        if ($this->content->kind() === MeasureKind::COUNT) {
            if ($this->unitWeight === null && $this->unitVolume === null) {
                throw new InvalidProduct('Un producto por unidades necesita el peso o el volumen de una unidad (basta uno de los dos).');
            }
            if ($this->unitWeight !== null && $this->unitWeight->kind() !== MeasureKind::WEIGHT) {
                throw new InvalidProduct('El peso de una unidad debe estar en una unidad de peso.');
            }
            if ($this->unitVolume !== null && $this->unitVolume->kind() !== MeasureKind::VOLUME) {
                throw new InvalidProduct('El volumen de una unidad debe estar en una unidad de volumen.');
            }

            return;
        }

        if ($this->unitWeight !== null || $this->unitVolume !== null) {
            throw new InvalidProduct('El peso y el volumen por unidad solo se usan en los productos por unidades.');
        }
    }
}
