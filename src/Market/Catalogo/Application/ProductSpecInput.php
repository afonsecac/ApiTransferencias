<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\ProductValue;
use App\Market\Catalogo\Domain\Quantity;

/**
 * Los datos editables de un producto tal como llegan a un caso de uso (sin depender del transporte HTTP): valores y
 * códigos de unidad como texto. toSpec() los convierte a objetos de dominio y lanza InvalidProduct si no cuadran.
 */
final readonly class ProductSpecInput
{
    /**
     * @param array<string, array{name?: string|null, description?: string|null}> $texts por idioma
     */
    public function __construct(
        public array $texts,
        public string $service,
        public string $subservice,
        public ?string $area,
        public ?float $contentValue,
        public ?string $contentUnit,
        public ?float $unitWeightValue,
        public ?string $unitWeightUnit,
        public ?float $unitVolumeValue,
        public ?string $unitVolumeUnit,
        public ?float $lengthMm,
        public ?float $widthMm,
        public ?float $heightMm,
        public ?string $deliveryKind,
        public ?string $deliveryUnit,
        public ?string $barcode,
        public ?string $valueCurrency = null,
        public ?string $valueMin = null,
        public ?string $valueMax = null,
    ) {
    }

    /**
     * @throws InvalidProduct
     */
    public function toSpec(): ProductSpec
    {
        return new ProductSpec(
            ProductTexts::fromArray($this->texts),
            Classification::of($this->service, $this->subservice, $this->area),
            $this->contentValue === null && ($this->contentUnit === null || $this->contentUnit === '')
                ? null
                : Quantity::of((float) $this->contentValue, self::unit((string) $this->contentUnit)),
            self::optionalQuantity($this->unitWeightValue, $this->unitWeightUnit, 'el peso por unidad'),
            self::optionalQuantity($this->unitVolumeValue, $this->unitVolumeUnit, 'el volumen por unidad'),
            $this->dimensions(),
            $this->deliveryBasis(),
            $this->barcode === null || trim($this->barcode) === '' ? null : trim($this->barcode),
            $this->value(),
        );
    }

    private function deliveryBasis(): ?DeliveryBasis
    {
        if (($this->deliveryKind === null || $this->deliveryKind === '') && ($this->deliveryUnit === null || $this->deliveryUnit === '')) {
            return null;
        }

        return DeliveryBasis::of(
            MeasureKind::tryFrom((string) $this->deliveryKind) ?? throw new InvalidProduct(sprintf('La base de tarifa "%s" no es válida.', (string) $this->deliveryKind)),
            self::unit((string) $this->deliveryUnit),
        );
    }

    /** Un producto con valor (digital) se indica con su moneda; el valor es fijo (solo mínimo) o variable (mínimo y máximo). */
    private function value(): ?ProductValue
    {
        $currency = $this->valueCurrency === null || trim($this->valueCurrency) === '' ? null : $this->valueCurrency;
        $min = $this->valueMin === null || trim($this->valueMin) === '' ? null : $this->valueMin;
        $max = $this->valueMax === null || trim($this->valueMax) === '' ? null : $this->valueMax;
        if ($currency === null && $min === null && $max === null) {
            return null;
        }
        if ($currency === null || $min === null) {
            throw new InvalidProduct('Un valor necesita su moneda y su importe (o el mínimo, si es de valor variable).');
        }
        $minAmount = Amount::fromString($min);

        return $max === null
            ? ProductValue::fixed($currency, $minAmount)
            : ProductValue::ranged($currency, $minAmount, Amount::fromString($max));
    }

    private static function unit(string $code): MeasureUnit
    {
        return MeasureUnit::tryFrom($code) ?? throw new InvalidProduct(sprintf('La unidad "%s" no es válida.', $code));
    }

    private static function optionalQuantity(?float $value, ?string $unit, string $what): ?Quantity
    {
        if ($value === null && ($unit === null || $unit === '')) {
            return null;
        }
        if ($value === null || $unit === null || $unit === '') {
            throw new InvalidProduct(sprintf('Para indicar %s hacen falta el valor y la unidad.', $what));
        }

        return Quantity::of($value, self::unit($unit));
    }

    private function dimensions(): ?Dimensions
    {
        $given = array_filter([$this->lengthMm, $this->widthMm, $this->heightMm], static fn (?float $d) => $d !== null);
        if ($given === []) {
            return null;
        }
        if (count($given) !== 3) {
            throw new InvalidProduct('Las dimensiones se indican las tres (largo, ancho y alto) o ninguna.');
        }

        return Dimensions::of((float) $this->lengthMm, (float) $this->widthMm, (float) $this->heightMm);
    }
}
