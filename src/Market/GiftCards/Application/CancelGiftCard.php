<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;

final class CancelGiftCard
{
    public function __construct(private readonly GiftCardRepository $cards)
    {
    }

    /**
     * Anula una tarjeta emitida o activa, con motivo; una canjeada no se anula.
     *
     * @throws GiftCardNotFound
     * @throws GiftCardTransitionInvalid
     * @throws InvalidGiftCard
     */
    public function __invoke(string $serial, string $reason, GiftCardActor $actor, ?\DateTimeImmutable $now = null): GiftCard
    {
        $now ??= new \DateTimeImmutable();
        $card = $this->cards->findBySerial($serial) ?? throw GiftCardNotFound::serial($serial);
        $card->cancel($reason, $now);
        $this->cards->save($card, GiftCardEvent::of((int) $card->id(), 'cancelled', $actor->name, trim($reason), $now));

        return $card;
    }
}
