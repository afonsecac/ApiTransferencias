<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/gift-cards/redeem. La clave de canje hace la operación idempotente.
 */
class RedeemGiftCardDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    protected ?string $pin;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    protected ?string $redemptionRef;

    public function __construct(?string $pin = null, ?string $redemptionRef = null)
    {
        $this->pin = $pin;
        $this->redemptionRef = $redemptionRef;
    }

    public function getPin(): ?string
    {
        return $this->pin;
    }

    public function setPin(?string $pin): void
    {
        $this->pin = $pin;
    }

    public function getRedemptionRef(): ?string
    {
        return $this->redemptionRef;
    }

    public function setRedemptionRef(?string $redemptionRef): void
    {
        $this->redemptionRef = $redemptionRef;
    }
}
