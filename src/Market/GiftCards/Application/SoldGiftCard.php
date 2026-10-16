<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;

/** El resultado de vender una tarjeta: la tarjeta activada y su PIN, que es lo que se entrega al comprador. */
final readonly class SoldGiftCard
{
    public function __construct(public GiftCard $card, public string $pin, public bool $replay)
    {
    }
}
