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
 * - Un producto lleva contenido o valor, no los dos: con valor (una gift card, COMR-276) es digital y no lleva nada de lo físico.
 * - La clasificación (servicio, subservicio y área) es obligatoria y dice de qué tipo es; no hay un campo de tipo aparte.
 */
final readonly class ProductSpec
{
    public function __construct(
        public ProductTexts $texts,
        public Classification $classification,
        public ?Quantity $content,
        public ?Quantity $unitWeight,
        public ?Quantity $unitVolume,
        public ?Dimensions $dimensions,
        public ?DeliveryBasis $deliveryBasis,
        public ?string $barcode = null,
        public ?ProductValue $value = null,
        /** Las unidades base con que se calculan los totales; sin él, las estándar (kilogramos y litros). */
        public ?MeasureSystem $measures = null,
    ) {
        if ($barcode !== null && preg_match('/^[0-9]{8,14}$/', $barcode) !== 1) {
            throw new InvalidProduct('El código de barras son entre 8 y 14 dígitos.');
        }

        if ($value !== null) {
            $this->validateDigital();

            return;
        }
        if ($content === null || $deliveryBasis === null) {
            throw new InvalidProduct('Un producto lleva contenido y base de tarifa de la entrega, o un valor (si es digital).');
        }

        $this->validateReferences($content);

        if ($deliveryBasis->kind() === MeasureKind::WEIGHT && $this->totalWeight() === null) {
            throw new InvalidProduct('Para tarifar por peso el producto necesita su peso (o el peso de una unidad).');
        }
        if ($deliveryBasis->kind() === MeasureKind::VOLUME && $this->totalVolume() === null) {
            throw new InvalidProduct('Para tarifar por volumen el producto necesita su volumen, el de una unidad o sus dimensiones.');
        }
    }

    /** Un producto con valor (una gift card) es digital: sin contenido físico ni entrega. */
    public function isDigital(): bool
    {
        return $this->value !== null;
    }

    /** Peso total en la unidad base de peso (kilogramos), o null si no se puede saber (o si es digital). */
    public function totalWeight(): ?float
    {
        if ($this->content === null) {
            return null;
        }

        return match ($this->content->kind()) {
            MeasureKind::WEIGHT => $this->content->baseValue(),
            MeasureKind::COUNT => $this->unitWeight === null ? null : round($this->content->baseValue() * $this->unitWeight->baseValue(), 6),
            MeasureKind::VOLUME => null,
        };
    }

    /** Volumen total en la unidad base de volumen (litros), o null si no se puede saber (o si es digital). */
    public function totalVolume(): ?float
    {
        if ($this->content === null) {
            return null;
        }
        $system = $this->measures ?? MeasureSystem::standard();

        return match ($this->content->kind()) {
            MeasureKind::VOLUME => $this->content->baseValue(),
            MeasureKind::COUNT => match (true) {
                $this->unitVolume !== null => round($this->content->baseValue() * $this->unitVolume->baseValue(), 6),
                $this->dimensions !== null => round($this->content->baseValue() * $this->dimensions->volume($system), 6),
                default => null,
            },
            MeasureKind::WEIGHT => $this->dimensions?->volume($system),
        };
    }

    private function validateDigital(): void
    {
        if ($this->content !== null || $this->unitWeight !== null || $this->unitVolume !== null || $this->dimensions !== null
            || $this->deliveryBasis !== null || $this->barcode !== null) {
            throw new InvalidProduct('Un producto con valor es digital: no lleva contenido, peso, volumen, dimensiones, base de entrega ni código de barras.');
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
