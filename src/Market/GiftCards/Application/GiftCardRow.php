<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\GiftCardStatus;

/** Una tarjeta con su producto y el estado que se muestra (vencida si pasó la caducidad). El PIN nunca viaja aquí. */
final readonly class GiftCardRow
{
    public GiftCardStatus $status;

    public function __construct(public GiftCard $card, public ?GiftCardProduct $product, \DateTimeImmutable $now)
    {
        $this->status = $card->effectiveStatus($now);
    }
}
