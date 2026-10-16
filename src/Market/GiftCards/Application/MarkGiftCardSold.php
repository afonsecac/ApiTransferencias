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

/**
 * La venta de una tarjeta la activa. Es el punto de enganche del flujo de venta (sellPackage() con el proveedor OWN), que aún no
 * está construido: hoy lo usan las pruebas y se podrá llamar desde la venta cuando exista.
 */
final class MarkGiftCardSold
{
    public function __construct(private readonly GiftCardRepository $cards)
    {
    }

    /**
     * @throws GiftCardNotFound
     * @throws GiftCardTransitionInvalid si no está emitida o venció
     * @throws InvalidGiftCard sin comprador o sin referencia de la venta
     */
    public function __invoke(string $serial, string $soldTo, string $saleRef, GiftCardActor $actor, ?\DateTimeImmutable $now = null): GiftCard
    {
        $now ??= new \DateTimeImmutable();
        if (trim($soldTo) === '' || trim($saleRef) === '') {
            throw new InvalidGiftCard('La venta necesita el comprador y su referencia.');
        }
        $card = $this->cards->findBySerial($serial) ?? throw GiftCardNotFound::serial($serial);
        $card->markSold($soldTo, $saleRef, $now);
        $this->cards->save($card, GiftCardEvent::of((int) $card->id(), 'sold', $actor->name, trim($soldTo) . ' · ' . trim($saleRef), $now));

        return $card;
    }
}
