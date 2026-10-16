<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/stock/adjust: suma (cantidad positiva) o resta (negativa) existencias. El motivo y el
 * comentario son obligatorios; la referencia (albarán, pedido…) es opcional.
 */
class AdjustStockDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $warehouseId;

    #[Assert\Choice(choices: ['product', 'combo'])]
    protected ?string $itemType;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $itemId;

    #[Assert\NotNull]
    #[Assert\NotEqualTo(0)]
    #[Assert\Range(min: -1000000, max: 1000000)]
    protected ?int $delta;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $reason;

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 500)]
    protected ?string $comment;

    #[Assert\Length(max: 80)]
    protected ?string $reference;

    public function __construct(?int $warehouseId = null, ?string $itemType = null, ?int $itemId = null, ?int $delta = null, ?string $reason = null, ?string $comment = null, ?string $reference = null)
    {
        $this->warehouseId = $warehouseId;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->delta = $delta;
        $this->reason = $reason;
        $this->comment = $comment;
        $this->reference = $reference;
    }

    public function getWarehouseId(): ?int
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?int $warehouseId): void
    {
        $this->warehouseId = $warehouseId;
    }

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(?string $itemType): void
    {
        $this->itemType = $itemType;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(?int $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getDelta(): ?int
    {
        return $this->delta;
    }

    public function setDelta(?int $delta): void
    {
        $this->delta = $delta;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }
}
