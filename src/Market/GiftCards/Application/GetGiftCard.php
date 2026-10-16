<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\GiftCards\Domain\GiftCardRepository;

final class GetGiftCard
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardProducts $products,
    ) {
    }

    /** @throws GiftCardNotFound */
    public function __invoke(string $serial, ?\DateTimeImmutable $now = null): GiftCardDetails
    {
        $card = $this->cards->findBySerial($serial) ?? throw GiftCardNotFound::serial($serial);

        return new GiftCardDetails($card, $this->products->find($card->productId()), $this->cards->events((int) $card->id()), $now ?? new \DateTimeImmutable(), $this->cards->findBatch($card->batchId()));
    }
}
