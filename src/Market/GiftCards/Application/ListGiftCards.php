<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardNumber;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\GiftCards\Domain\GiftCardRepository;

final class ListGiftCards
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardProducts $products,
    ) {
    }

    /**
     * @param string|null $status `issued`, `active`, `redeemed`, `cancelled` o `expired` (el estado que se muestra)
     * @param string|null $serial parte del número de tarjeta (con o sin espacios)
     *
     * @return list<GiftCardRow>
     */
    public function __invoke(?int $productId, ?string $status, ?int $batchId, ?string $serial, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $cards = $this->cards->search($productId, $batchId, $serial === null || GiftCardNumber::normalize($serial) === '' ? null : GiftCardNumber::normalize($serial));
        $products = $this->products->findMany(array_values(array_unique(array_map(static fn (GiftCard $c) => $c->productId(), $cards))));
        $rows = array_map(static fn (GiftCard $c) => new GiftCardRow($c, $products[$c->productId()] ?? null, $now), $cards);
        if ($status !== null) {
            $rows = array_values(array_filter($rows, static fn (GiftCardRow $r) => $r->status->value === $status));
        }

        return $rows;
    }
}
