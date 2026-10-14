<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Los datos editables de un producto (COMR-273): lo que comparten el alta y la edición. Un producto físico lleva su
 * contenido y su base de tarifa; una gift card (COMR-276) lleva categoría, moneda y valor (importes como texto con punto
 * decimal) y nada de lo físico. Las reglas entre los campos las valida el dominio. Los valores van con el código de su unidad
 * (g, kg, oz, lb, ml, l, fl_oz, unit); la unidad original se conserva para mostrarla.
 */
abstract class ProductSpecDto implements IInput
{
    protected const WEIGHT_UNITS = ['g', 'kg', 'oz', 'lb'];
    protected const VOLUME_UNITS = ['ml', 'l', 'fl_oz'];
    protected const UNITS = ['g', 'kg', 'oz', 'lb', 'ml', 'l', 'fl_oz', 'unit'];

    /** @var array<string, array{name?: string|null, description?: string|null}>|null */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    protected ?array $texts;

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

    #[Assert\Choice(choices: ['weight', 'volume'])]
    protected ?string $deliveryKind;

    #[Assert\Choice(choices: self::UNITS)]
    protected ?string $deliveryUnit;

    #[Assert\Choice(choices: ['supermarket'])]
    protected ?string $area;

    #[Assert\Regex(pattern: '/^[0-9]{8,14}$/', message: 'El código de barras son entre 8 y 14 dígitos.')]
    protected ?string $barcode;

    #[Assert\Choice(choices: ['retail', 'gaming', 'cash_cards', 'food', 'entertainment', 'travel_transport'])]
    protected ?string $giftCardCategory;

    #[Assert\Length(max: 3)]
    protected ?string $giftCardCurrency;

    #[Assert\Regex(pattern: '/^\d{1,12}(\.\d{1,2})?$/', message: 'El importe es un número positivo con hasta dos decimales (punto decimal).')]
    protected ?string $giftCardMin;

    #[Assert\Regex(pattern: '/^\d{1,12}(\.\d{1,2})?$/', message: 'El importe es un número positivo con hasta dos decimales (punto decimal).')]
    protected ?string $giftCardMax;

    /**
     * @param array<string, array{name?: string|null, description?: string|null}>|null $texts
     */
    public function __construct(
        ?array $texts = null,
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
        ?string $area = null,
        ?string $barcode = null,
        ?string $giftCardCategory = null,
        ?string $giftCardCurrency = null,
        ?string $giftCardMin = null,
        ?string $giftCardMax = null,
    ) {
        $this->texts = $texts;
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
        $this->area = $area;
        $this->barcode = $barcode;
        $this->giftCardCategory = $giftCardCategory;
        $this->giftCardCurrency = $giftCardCurrency;
        $this->giftCardMin = $giftCardMin;
        $this->giftCardMax = $giftCardMax;
    }

    public function getTexts(): ?array
    {
        return $this->texts;
    }

    public function setTexts(?array $texts): void
    {
        $this->texts = $texts;
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

    public function getDeliveryUnit(): ?string
    {
        return $this->deliveryUnit;
    }

    public function setDeliveryUnit(?string $deliveryUnit): void
    {
        $this->deliveryUnit = $deliveryUnit;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): void
    {
        $this->area = $area;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
    }

    public function getGiftCardCategory(): ?string
    {
        return $this->giftCardCategory;
    }

    public function setGiftCardCategory(?string $giftCardCategory): void
    {
        $this->giftCardCategory = $giftCardCategory;
    }

    public function getGiftCardCurrency(): ?string
    {
        return $this->giftCardCurrency;
    }

    public function setGiftCardCurrency(?string $giftCardCurrency): void
    {
        $this->giftCardCurrency = $giftCardCurrency;
    }

    public function getGiftCardMin(): ?string
    {
        return $this->giftCardMin;
    }

    public function setGiftCardMin(?string $giftCardMin): void
    {
        $this->giftCardMin = $giftCardMin;
    }

    public function getGiftCardMax(): ?string
    {
        return $this->giftCardMax;
    }

    public function setGiftCardMax(?string $giftCardMax): void
    {
        $this->giftCardMax = $giftCardMax;
    }

}
