<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/gift-cards/{serial}/cancel.
 */
class CancelGiftCardDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    protected ?string $reason;

    public function __construct(?string $reason = null)
    {
        $this->reason = $reason;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }
}
