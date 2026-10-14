<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/products. El sku es único e inmutable; sin vendedor, el producto es del vendedor de
 * plataforma. Nace en borrador.
 */
class CreateProductDto extends ProductSpecDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    protected ?string $sku;

    #[Assert\Positive]
    protected ?int $sellerId;

    /**
     * @param array<string, array{name?: string|null, description?: string|null}>|null $texts
     */
    public function __construct(
        ?string $sku = null,
        ?int $sellerId = null,
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
        parent::__construct(texts: $texts, contentValue: $contentValue, contentUnit: $contentUnit, unitWeightValue: $unitWeightValue, unitWeightUnit: $unitWeightUnit, unitVolumeValue: $unitVolumeValue, unitVolumeUnit: $unitVolumeUnit, lengthMm: $lengthMm, widthMm: $widthMm, heightMm: $heightMm, deliveryKind: $deliveryKind, deliveryUnit: $deliveryUnit, area: $area, barcode: $barcode, giftCardCategory: $giftCardCategory, giftCardCurrency: $giftCardCurrency, giftCardMin: $giftCardMin, giftCardMax: $giftCardMax);
        $this->sku = $sku;
        $this->sellerId = $sellerId;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    public function setSellerId(?int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }
}
