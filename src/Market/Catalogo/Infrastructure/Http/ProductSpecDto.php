<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use App\Market\Catalogo\Domain\Classification;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Los datos editables de un producto (COMR-273): lo que comparten el alta y la edición. La clasificación (servicio,
 * subservicio y área) dice de qué tipo es, sin un campo de tipo aparte. Lleva contenido y base de tarifa de la entrega, o un
 * valor (digital, como las gift cards: moneda e importes como texto con punto decimal) y nada de lo físico. Las reglas entre
 * los campos las valida el dominio. Los valores van con el código de su unidad (g, kg, oz, lb, t, ml, l, fl_oz, gal, unit, pair, dozen); la
 * unidad original se conserva para mostrarla.
 */
abstract class ProductSpecDto implements IInput
{
    protected const WEIGHT_UNITS = ['g', 'kg', 'oz', 'lb', 't'];
    protected const VOLUME_UNITS = ['ml', 'l', 'fl_oz', 'gal'];
    protected const UNITS = ['g', 'kg', 'oz', 'lb', 't', 'ml', 'l', 'fl_oz', 'gal', 'unit', 'pair', 'dozen'];

    /** @var array<string, array{name?: string|null, description?: string|null}>|null */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    protected ?array $texts;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: Classification::SERVICES)]
    protected ?string $service;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: Classification::SUBSERVICES)]
    protected ?string $subservice;

    #[Assert\Choice(choices: ['Supermarket'])]
    protected ?string $area;

    #[Assert\Positive]
    protected ?float $contentValue;

    #[Assert\Choice(choices: self::UNITS)]
    protected ?string $contentUnit;

    #[Assert\Positive]
    protected ?float $unitWeightValue;

    #[Assert\Choice(choices: self::WEIGHT_UNITS)]
    protected ?string $unitWeightUnit;

    #[Assert\Positive]
    protected ?float $unitVolumeValue;

    #[Assert\Choice(choices: self::VOLUME_UNITS)]
    protected ?string $unitVolumeUnit;

    #[Assert\Positive]
    protected ?float $lengthMm;

    #[Assert\Positive]
    protected ?float $widthMm;

    #[Assert\Positive]
    protected ?float $heightMm;

    #[Assert\Choice(choices: ['mm', 'cm', 'm', 'in', 'ft'])]
    protected ?string $dimensionsUnit;

    #[Assert\Choice(choices: ['weight', 'volume'])]
    protected ?string $deliveryKind;

    #[Assert\Choice(choices: self::UNITS)]
    protected ?string $deliveryUnit;

    #[Assert\Regex(pattern: '/^[0-9]{8,14}$/', message: 'El código de barras son entre 8 y 14 dígitos.')]
    protected ?string $barcode;

    #[Assert\Length(max: 3)]
    protected ?string $valueCurrency;

    #[Assert\Regex(pattern: '/^\d{1,12}(\.\d{1,2})?$/', message: 'El importe es un número positivo con hasta dos decimales (punto decimal).')]
    protected ?string $valueMin;

    #[Assert\Regex(pattern: '/^\d{1,12}(\.\d{1,2})?$/', message: 'El importe es un número positivo con hasta dos decimales (punto decimal).')]
    protected ?string $valueMax;

    /** La marca (opcional): tiene que ser del vendedor del producto. */
    #[Assert\Positive]
    protected ?int $brandId;

    /**
     * Las categorías del producto (al menos una para publicarlo).
     *
     * @var list<int>|null
     */
    #[Assert\Type('array')]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Type('int'), new Assert\Positive()])]
    protected ?array $categoryIds;

    /**
     * @param array<string, array{name?: string|null, description?: string|null}>|null $texts
     * @param list<int>|null $categoryIds
     */
    public function __construct(
        ?array $texts = null,
        ?string $service = null,
        ?string $subservice = null,
        ?string $area = null,
        ?float $contentValue = null,
        ?string $contentUnit = null,
        ?float $unitWeightValue = null,
        ?string $unitWeightUnit = null,
        ?float $unitVolumeValue = null,
        ?string $unitVolumeUnit = null,
        ?float $lengthMm = null,
        ?float $widthMm = null,
        ?float $heightMm = null,
        ?string $deliveryKind = null,
        ?string $deliveryUnit = null,
        ?string $barcode = null,
        ?string $valueCurrency = null,
        ?string $valueMin = null,
        ?string $valueMax = null,
        ?int $brandId = null,
        ?array $categoryIds = null,
        ?string $dimensionsUnit = null,
    ) {
        $this->dimensionsUnit = $dimensionsUnit;
        $this->brandId = $brandId;
        $this->categoryIds = $categoryIds;
        $this->texts = $texts;
        $this->service = $service;
        $this->subservice = $subservice;
        $this->area = $area;
        $this->contentValue = $contentValue;
        $this->contentUnit = $contentUnit;
        $this->unitWeightValue = $unitWeightValue;
        $this->unitWeightUnit = $unitWeightUnit;
        $this->unitVolumeValue = $unitVolumeValue;
        $this->unitVolumeUnit = $unitVolumeUnit;
        $this->lengthMm = $lengthMm;
        $this->widthMm = $widthMm;
        $this->heightMm = $heightMm;
        $this->deliveryKind = $deliveryKind;
        $this->deliveryUnit = $deliveryUnit;
        $this->barcode = $barcode;
        $this->valueCurrency = $valueCurrency;
        $this->valueMin = $valueMin;
        $this->valueMax = $valueMax;
    }

    public function getTexts(): ?array
    {
        return $this->texts;
    }

    public function setTexts(?array $texts): void
    {
        $this->texts = $texts;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): void
    {
        $this->service = $service;
    }

    public function getSubservice(): ?string
    {
        return $this->subservice;
    }

    public function setSubservice(?string $subservice): void
    {
        $this->subservice = $subservice;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): void
    {
        $this->area = $area;
    }

    public function getContentValue(): ?float
    {
        return $this->contentValue;
    }

    public function setContentValue(?float $contentValue): void
    {
        $this->contentValue = $contentValue;
    }

    public function getContentUnit(): ?string
    {
        return $this->contentUnit;
    }

    public function setContentUnit(?string $contentUnit): void
    {
        $this->contentUnit = $contentUnit;
    }

    public function getUnitWeightValue(): ?float
    {
        return $this->unitWeightValue;
    }

    public function setUnitWeightValue(?float $unitWeightValue): void
    {
        $this->unitWeightValue = $unitWeightValue;
    }

    public function getUnitWeightUnit(): ?string
    {
        return $this->unitWeightUnit;
    }

    public function setUnitWeightUnit(?string $unitWeightUnit): void
    {
        $this->unitWeightUnit = $unitWeightUnit;
    }

    public function getUnitVolumeValue(): ?float
    {
        return $this->unitVolumeValue;
    }

    public function setUnitVolumeValue(?float $unitVolumeValue): void
    {
        $this->unitVolumeValue = $unitVolumeValue;
    }

    public function getUnitVolumeUnit(): ?string
    {
        return $this->unitVolumeUnit;
    }

    public function setUnitVolumeUnit(?string $unitVolumeUnit): void
    {
        $this->unitVolumeUnit = $unitVolumeUnit;
    }

    public function getLengthMm(): ?float
    {
        return $this->lengthMm;
    }

    public function setLengthMm(?float $lengthMm): void
    {
        $this->lengthMm = $lengthMm;
    }

    public function getWidthMm(): ?float
    {
        return $this->widthMm;
    }

    public function setWidthMm(?float $widthMm): void
    {
        $this->widthMm = $widthMm;
    }

    public function getHeightMm(): ?float
    {
        return $this->heightMm;
    }

    public function setHeightMm(?float $heightMm): void
    {
        $this->heightMm = $heightMm;
    }

    public function getDeliveryKind(): ?string
    {
        return $this->deliveryKind;
    }

    public function setDeliveryKind(?string $deliveryKind): void
    {
        $this->deliveryKind = $deliveryKind;
    }

    /** La unidad en que se escribieron el largo, el ancho y el alto (que siempre llegan en milímetros); mm por defecto. */
    public function getDimensionsUnit(): ?string
    {
        return $this->dimensionsUnit;
    }

    public function setDimensionsUnit(?string $dimensionsUnit): void
    {
        $this->dimensionsUnit = $dimensionsUnit;
    }

    public function getDeliveryUnit(): ?string
    {
        return $this->deliveryUnit;
    }

    public function setDeliveryUnit(?string $deliveryUnit): void
    {
        $this->deliveryUnit = $deliveryUnit;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
    }

    public function getValueCurrency(): ?string
    {
        return $this->valueCurrency;
    }

    public function setValueCurrency(?string $valueCurrency): void
    {
        $this->valueCurrency = $valueCurrency;
    }

    public function getValueMin(): ?string
    {
        return $this->valueMin;
    }

    public function setValueMin(?string $valueMin): void
    {
        $this->valueMin = $valueMin;
    }

    public function getValueMax(): ?string
    {
        return $this->valueMax;
    }

    public function setValueMax(?string $valueMax): void
    {
        $this->valueMax = $valueMax;
    }


    public function getBrandId(): ?int
    {
        return $this->brandId;
    }

    public function setBrandId(?int $brandId): void
    {
        $this->brandId = $brandId;
    }

    /**
     * @return list<int>|null
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }

    /**
     * @param list<int>|null $categoryIds
     */
    public function setCategoryIds(?array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
    }
}
