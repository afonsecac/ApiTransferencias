<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fija el CommunicationProduct que representa una CommunicationPromotions
 * para un proveedor concreto — ver CommunicationPromotionBindingService::setBinding().
 */
class SetPromotionProviderProductDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $productId;

    public function __construct(?int $productId = null)
    {
        $this->productId = $productId;
    }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): void { $this->productId = $v; }
}
