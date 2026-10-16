<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardBatch;

final readonly class IssuedBatch
{
    /** @param list<GiftCard> $cards */
    public function __construct(public GiftCardBatch $batch, public array $cards)
    {
    }
}
