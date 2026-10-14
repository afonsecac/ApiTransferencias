<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductArea;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
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
        public float $contentValue,
        public string $contentUnit,
        public ?float $unitWeightValue,
        public ?string $unitWeightUnit,
        public ?float $unitVolumeValue,
        public ?string $unitVolumeUnit,
        public ?float $lengthMm,
        public ?float $widthMm,
        public ?float $heightMm,
        public string $deliveryKind,
        public string $deliveryUnit,
        public ?string $area,
        public ?string $barcode,
    ) {
    }

    /**
     * @throws InvalidProduct
     */
    public function toSpec(): ProductSpec
    {
        return new ProductSpec(
            ProductTexts::fromArray($this->texts),
            Quantity::of($this->contentValue, self::unit($this->contentUnit)),
            self::optionalQuantity($this->unitWeightValue, $this->unitWeightUnit, 'el peso por unidad'),
            self::optionalQuantity($this->unitVolumeValue, $this->unitVolumeUnit, 'el volumen por unidad'),
            $this->dimensions(),
            DeliveryBasis::of(
                MeasureKind::tryFrom($this->deliveryKind) ?? throw new InvalidProduct(sprintf('La base de tarifa "%s" no es válida.', $this->deliveryKind)),
                self::unit($this->deliveryUnit),
            ),
            $this->area === null || $this->area === ''
                ? null
                : (ProductArea::tryFrom($this->area) ?? throw new InvalidProduct(sprintf('El área "%s" no es válida.', $this->area))),
            $this->barcode === null || trim($this->barcode) === '' ? null : trim($this->barcode),
        );
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
