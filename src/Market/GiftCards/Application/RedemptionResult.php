<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;

final readonly class RedemptionResult
{
    public function __construct(public GiftCard $card, public bool $replay)
    {
    }
}
