<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardNotFound;
use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Domain\GiftCardTransitionInvalid;
use App\Market\GiftCards\Domain\InvalidGiftCard;

/**
 * La venta de una tarjeta la activa y es el ÚNICO camino por el que sale el PIN: se le entrega al comprador en el momento de la
 * compra y la administración no puede verlo nunca. Es el punto de enganche del flujo de venta (sellPackage() con el proveedor OWN),
 * que aún no está construido: hoy lo usan las pruebas. Es idempotente por venta: repetir la misma venta devuelve el mismo PIN sin
 * dejar otro hecho en la traza (el flujo de venta puede reintentar); otra venta distinta de la misma tarjeta se rechaza.
 */
final class MarkGiftCardSold
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardPinVault $vault,
    ) {
    }

    /**
     * @throws GiftCardNotFound
     * @throws GiftCardTransitionInvalid si no está emitida, venció o ya se vendió en otra venta
     * @throws InvalidGiftCard sin comprador o sin referencia de la venta
     */
    public function __invoke(string $serial, string $soldTo, string $saleRef, GiftCardActor $actor, ?\DateTimeImmutable $now = null): SoldGiftCard
    {
        $now ??= new \DateTimeImmutable();
        if (trim($soldTo) === '' || trim($saleRef) === '') {
            throw new InvalidGiftCard('La venta necesita el comprador y su referencia.');
        }
        $card = $this->cards->findBySerial($serial) ?? throw GiftCardNotFound::serial($serial);
        $first = $card->markSold($soldTo, $saleRef, $now);
        if ($first) {
            $this->cards->save($card, GiftCardEvent::of((int) $card->id(), 'sold', $actor->name, trim($soldTo) . ' · ' . trim($saleRef), $now));
        }

        return new SoldGiftCard($card, $this->vault->decrypt($card->pinCipher()), !$first);
    }
}
