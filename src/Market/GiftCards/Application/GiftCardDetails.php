<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\GiftCardStatus;

final readonly class GiftCardDetails
{
    public GiftCardStatus $status;

    /** @param list<GiftCardEvent> $events */
    public function __construct(public GiftCard $card, public ?GiftCardProduct $product, public array $events, \DateTimeImmutable $now, public ?GiftCardBatch $batch = null)
    {
        $this->status = $card->effectiveStatus($now);
    }
}
