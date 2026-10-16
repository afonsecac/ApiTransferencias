<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

interface GiftCardRepository
{
    /** Ejecuta el trabajo en una transacción: o se guarda todo el lote o nada. */
    public function transactional(callable $work): mixed;

    /**
     * @param list<string> $serials
     *
     * @return list<string> los que ya existen (para volver a generarlos)
     */
    public function takenSerials(array $serials): array;

    public function saveBatch(GiftCardBatch $batch): void;

    public function findBatch(int $id): ?GiftCardBatch;

    /** @param list<GiftCard> $cards */
    public function saveCards(array $cards): void;

    public function save(GiftCard $card, ?GiftCardEvent $event = null): void;

    public function addEvent(GiftCardEvent $event): void;

    public function findBySerial(string $serial): ?GiftCard;

    public function findByPinHash(string $hash): ?GiftCard;

    /** @return list<GiftCard> por serie */
    public function search(?int $productId, ?int $batchId, ?string $serialLike): array;

    /** @return list<GiftCard> las canjeadas, la más reciente primero */
    public function redeemed(int $limit): array;

    /** @return list<GiftCardEvent> en orden cronológico */
    public function events(int $cardId): array;
}
