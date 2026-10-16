<?php

declare(strict_types=1);

namespace App\Tests\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardRepository;

final class InMemoryGiftCardRepository implements GiftCardRepository
{
    /** @var array<string, GiftCard> */
    public array $cards = [];
    /** @var list<GiftCardEvent> */
    public array $events = [];
    /** @var list<GiftCardBatch> */
    public array $batches = [];
    private int $nextId = 1;

    public function transactional(callable $work): mixed
    {
        return $work();
    }

    public function takenSerials(array $serials): array
    {
        return array_values(array_filter($serials, fn (string $serial) => isset($this->cards[$serial])));
    }

    public function saveBatch(GiftCardBatch $batch): void
    {
        (new \ReflectionProperty($batch, 'id'))->setValue($batch, count($this->batches) + 1);
        $this->batches[] = $batch;
    }

    public function findBatch(int $id): ?GiftCardBatch
    {
        return $this->batches[$id - 1] ?? null;
    }

    public function saveCards(array $cards): void
    {
        foreach ($cards as $card) {
            $this->save($card);
        }
    }

    public function save(GiftCard $card, ?GiftCardEvent $event = null): void
    {
        if ($card->id() === null) {
            (new \ReflectionProperty($card, 'id'))->setValue($card, $this->nextId++);
        }
        $this->cards[$card->serial()] = $card;
        if ($event !== null) {
            $this->addEvent($event);
        }
    }

    public function addEvent(GiftCardEvent $event): void
    {
        $this->events[] = $event;
    }

    public function findBySerial(string $serial): ?GiftCard
    {
        return $this->cards[$serial] ?? null;
    }

    public function findByPinHash(string $hash): ?GiftCard
    {
        foreach ($this->cards as $card) {
            if ($card->pinHash() === $hash) {
                return $card;
            }
        }

        return null;
    }

    public function search(?int $productId, ?int $batchId, ?string $serialLike): array
    {
        return array_values(array_filter(
            $this->cards,
            static fn (GiftCard $c) => ($productId === null || $c->productId() === $productId)
                && ($batchId === null || $c->batchId() === $batchId)
                && ($serialLike === null || str_contains($c->serial(), $serialLike)),
        ));
    }

    public function redeemed(int $limit): array
    {
        $found = array_values(array_filter($this->cards, static fn (GiftCard $c) => $c->redeemedAt() !== null));
        usort($found, static fn (GiftCard $a, GiftCard $b) => $b->redeemedAt() <=> $a->redeemedAt());

        return array_slice($found, 0, $limit);
    }

    public function events(int $cardId): array
    {
        return array_values(array_filter($this->events, static fn (GiftCardEvent $e) => $e->cardId() === $cardId));
    }
}
