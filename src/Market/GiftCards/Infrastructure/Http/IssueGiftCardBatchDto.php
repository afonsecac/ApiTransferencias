<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/gift-cards/batches: un lote por rango. Emite `perValue` tarjetas de cada valor desde `min` hasta
 * `max` de `step` en `step` (con `min` = `max` es un solo valor y no hace falta paso). En un producto de valor fijo no se indican
 * `min`, `max` ni `step`: solo cuántas tarjetas.
 */
class IssueGiftCardBatchDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $productId;

    #[Assert\Length(max: 15)]
    protected ?string $min;

    #[Assert\Length(max: 15)]
    protected ?string $max;

    #[Assert\Length(max: 15)]
    protected ?string $step;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 1000)]
    protected ?int $perValue;

    #[Assert\Length(max: 10)]
    protected ?string $expiresAt;

    #[Assert\Positive]
    protected ?int $costCenterId;

    public function __construct(?int $productId = null, ?string $min = null, ?string $max = null, ?string $step = null, ?int $perValue = 1, ?string $expiresAt = null, ?int $costCenterId = null)
    {
        $this->productId = $productId;
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
        $this->perValue = $perValue;
        $this->expiresAt = $expiresAt;
        $this->costCenterId = $costCenterId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
    }

    public function getMin(): ?string
    {
        return $this->min;
    }

    public function setMin(?string $min): void
    {
        $this->min = $min;
    }

    public function getMax(): ?string
    {
        return $this->max;
    }

    public function setMax(?string $max): void
    {
        $this->max = $max;
    }

    public function getStep(): ?string
    {
        return $this->step;
    }

    public function setStep(?string $step): void
    {
        $this->step = $step;
    }

    public function getPerValue(): ?int
    {
        return $this->perValue;
    }

    public function setPerValue(?int $perValue): void
    {
        $this->perValue = $perValue;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?string $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getCostCenterId(): ?int
    {
        return $this->costCenterId;
    }

    public function setCostCenterId(?int $costCenterId): void
    {
        $this->costCenterId = $costCenterId;
    }
}
