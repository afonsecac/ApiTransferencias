<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/stock/safety.
 */
class SetSafetyStockDto implements IInput
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
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(1000000)]
    protected ?int $safety;

    public function __construct(?int $warehouseId = null, ?string $itemType = null, ?int $itemId = null, ?int $safety = null)
    {
        $this->warehouseId = $warehouseId;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->safety = $safety;
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

    public function getSafety(): ?int
    {
        return $this->safety;
    }

    public function setSafety(?int $safety): void
    {
        $this->safety = $safety;
    }
}
