<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/gift-cards: una tarjeta individual. El valor solo se pide en productos de valor variable
 * (importe con dos decimales); en uno de valor fijo lo pone el producto. Caducidad AAAA-MM-DD posterior a hoy; centro de costo por
 * defecto el principal del vendedor.
 */
class IssueGiftCardDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $productId;

    #[Assert\Length(max: 15)]
    protected ?string $value;

    #[Assert\Length(max: 10)]
    protected ?string $expiresAt;

    #[Assert\Positive]
    protected ?int $costCenterId;

    public function __construct(?int $productId = null, ?string $value = null, ?string $expiresAt = null, ?int $costCenterId = null)
    {
        $this->productId = $productId;
        $this->value = $value;
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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
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
