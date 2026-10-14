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
 * - Una gift card (COMR-276) es un producto digital: lleva su valor y categoría y nada de lo físico.
 */
final readonly class ProductSpec
{
    public function __construct(
        public ProductTexts $texts,
        public ?Quantity $content,
        public ?Quantity $unitWeight,
        public ?Quantity $unitVolume,
        public ?Dimensions $dimensions,
        public ?DeliveryBasis $deliveryBasis,
        public ?ProductArea $area = null,
        public ?string $barcode = null,
        public ?GiftCard $giftCard = null,
    ) {
        if ($barcode !== null && preg_match('/^[0-9]{8,14}$/', $barcode) !== 1) {
            throw new InvalidProduct('El código de barras son entre 8 y 14 dígitos.');
        }

        if ($giftCard !== null) {
            $this->validateDigital();
            if ($area !== null && $area->subservice() !== $giftCard->category()) {
                throw new InvalidProduct(sprintf('El área "%s" solo existe bajo el subservicio "%s".', $area->value, $area->subservice()->value));
            }

            return;
        }
        if ($content === null || $deliveryBasis === null) {
            throw new InvalidProduct('Un producto físico necesita su contenido y la base de tarifa de la entrega.');
        }

        $this->validateReferences($content);

        if ($deliveryBasis->kind() === MeasureKind::WEIGHT && $this->totalWeightG() === null) {
            throw new InvalidProduct('Para tarifar por peso el producto necesita su peso (o el peso de una unidad).');
        }
        if ($deliveryBasis->kind() === MeasureKind::VOLUME && $this->totalVolumeMl() === null) {
            throw new InvalidProduct('Para tarifar por volumen el producto necesita su volumen, el de una unidad o sus dimensiones.');
        }
    }

    /** Una gift card es un producto digital: sin contenido físico ni entrega. */
    public function isDigital(): bool
    {
        return $this->giftCard !== null;
    }

    /** Peso total en gramos, o null si no se puede saber (o si es digital). */
    public function totalWeightG(): ?float
    {
        if ($this->content === null) {
            return null;
        }

        return match ($this->content->kind()) {
            MeasureKind::WEIGHT => $this->content->baseValue(),
            MeasureKind::COUNT => $this->unitWeight === null ? null : round($this->content->baseValue() * $this->unitWeight->baseValue(), 3),
            MeasureKind::VOLUME => null,
        };
    }

    /** Volumen total en mililitros (= cm³), o null si no se puede saber (o si es digital). */
    public function totalVolumeMl(): ?float
    {
        if ($this->content === null) {
            return null;
        }

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

    private function validateDigital(): void
    {
        if ($this->content !== null || $this->unitWeight !== null || $this->unitVolume !== null || $this->dimensions !== null
            || $this->deliveryBasis !== null || $this->barcode !== null) {
            throw new InvalidProduct('Una gift card es un producto digital: no lleva contenido, peso, volumen, dimensiones, base de entrega ni código de barras.');
        }
    }

    private function validateReferences(Quantity $content): void
    {
        if ($content->kind() === MeasureKind::COUNT) {
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
