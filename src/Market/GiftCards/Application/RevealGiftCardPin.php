<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Market\GiftCards\Domain\GiftCardRepository;

final class RevealGiftCardPin
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardPinVault $vault,
    ) {
    }

    /**
     * Devuelve el PIN en claro y deja registro de quién y cuándo lo pidió: el PIN nunca aparece en los listados.
     *
     * @throws GiftCardNotFound
     */
    public function __invoke(string $serial, GiftCardActor $actor, ?\DateTimeImmutable $now = null): string
    {
        $card = $this->cards->findBySerial($serial) ?? throw GiftCardNotFound::serial($serial);
        $this->cards->addEvent(GiftCardEvent::of((int) $card->id(), 'pin_revealed', $actor->name, null, $now ?? new \DateTimeImmutable()));

        return $this->vault->decrypt($card->pinCipher());
    }
}
