<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\GiftCards\Domain\GiftCardRepository;

final class ListRedemptions
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardProducts $products,
    ) {
    }

    /** @return list<GiftCardRow> las tarjetas canjeadas, la más reciente primero */
    public function __invoke(int $limit = 50): array
    {
        $cards = $this->cards->redeemed(max(1, min($limit, 200)));
        $products = $this->products->findMany(array_values(array_unique(array_map(static fn (GiftCard $c) => $c->productId(), $cards))));
        $now = new \DateTimeImmutable();

        return array_map(static fn (GiftCard $c) => new GiftCardRow($c, $products[$c->productId()] ?? null, $now), $cards);
    }
}
