<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardAlreadyRedeemed;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;

final class RedeemGiftCard
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardPinVault $vault,
    ) {
    }

    /**
     * Canjea la tarjeta de ese PIN dentro de la plataforma. La clave de canje hace la operación idempotente: repetirla con la misma
     * clave devuelve el mismo canje sin duplicar nada; con otra clave sobre una tarjeta ya canjeada es un error. El abono del valor
     * al saldo del cliente no forma parte de esta historia: queda registrado quién, cuándo, con qué clave y a qué centro de costo.
     *
     * @throws InvalidGiftCard sin clave de canje
     * @throws GiftCardNotFound si ningún PIN coincide
     * @throws GiftCardTransitionInvalid si no está activa o venció
     * @throws GiftCardAlreadyRedeemed
     */
    public function __invoke(string $pin, string $redemptionRef, GiftCardActor $actor, ?\DateTimeImmutable $now = null): RedemptionResult
    {
        $now ??= new \DateTimeImmutable();
        $redemptionRef = trim($redemptionRef);
        if ($redemptionRef === '' || mb_strlen($redemptionRef) > 80) {
            throw new InvalidGiftCard('El canje necesita una clave de hasta 80 caracteres.');
        }
        $card = $this->cards->findByPinHash($this->vault->hash($pin)) ?? throw GiftCardNotFound::pin();

        $first = $card->redeem($redemptionRef, $now);
        if ($first) {
            $this->cards->save($card, GiftCardEvent::of((int) $card->id(), 'redeemed', $actor->name, $redemptionRef, $now));
        }

        return new RedemptionResult($card, !$first);
    }
}
