<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Market\GiftCards\Domain\GiftCardProductInvalid;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\GiftCards\Domain\GiftCardRepository;
use App\Market\GiftCards\Domain\InvalidGiftCard;

final class IssueGiftCardBatch
{
    public function __construct(
        private readonly GiftCardRepository $cards,
        private readonly GiftCardProducts $products,
        private readonly GiftCardPinVault $vault,
    ) {
    }

    /**
     * Emite un lote de tarjetas de un producto digital con valor: serie consecutiva, PIN no adivinable (cifrado y con huella) y el
     * centro de costo al que se imputa la emisión (por defecto el principal del vendedor del producto). Un producto de valor fijo
     * emite siempre ese valor; uno de valor variable pide el valor dentro de su rango. Todo el lote se guarda o nada.
     *
     * @param string|null $value importe decimal con dos decimales (solo en productos de valor variable)
     * @param string|null $expiresAt fecha `Y-m-d` posterior a hoy, o nada si no caduca
     *
     * @throws GiftCardProductInvalid
     * @throws InvalidGiftCard
     */
    public function __invoke(int $productId, int $quantity, ?string $value, ?string $expiresAt, ?int $costCenterId, GiftCardActor $actor, ?\DateTimeImmutable $now = null): IssuedBatch
    {
        $now ??= new \DateTimeImmutable();
        $product = $this->products->find($productId) ?? throw GiftCardProductInvalid::unknown($productId);
        if (!$product->digital || $product->currency === '') {
            throw GiftCardProductInvalid::notAGiftCard($product->name);
        }
        if ($quantity < 1 || $quantity > GiftCardBatch::MAX_QUANTITY) {
            throw new InvalidGiftCard(sprintf('Un lote lleva entre 1 y %d tarjetas.', GiftCardBatch::MAX_QUANTITY));
        }

        $valueMinor = $this->valueMinor($product->fixed, $product->minMinor, $product->maxMinor, $value);
        $expires = $this->expiry($expiresAt, $now);
        $center = $costCenterId === null ? $product->principalCostCenter() : $product->activeCostCenter($costCenterId);
        if ($center === null) {
            throw new InvalidGiftCard('El centro de costo debe ser un centro activo del vendedor del producto.');
        }

        /** @var IssuedBatch $issued */
        $issued = $this->cards->transactional(function () use ($product, $quantity, $valueMinor, $expires, $center, $actor, $now): IssuedBatch {
            $batch = GiftCardBatch::open($product->id, $quantity, $expires, $center->id, $actor->name, $now);
            $this->cards->saveBatch($batch);

            $issued = [];
            foreach ($this->cards->nextSerialNumbers($quantity) as $number) {
                $pin = $this->vault->generate();
                $issued[] = GiftCard::issue((int) $batch->id(), $product->id, sprintf('GC-%06d', $number), $valueMinor, $product->currency, $this->vault->encrypt($pin), $this->vault->hash($pin), $expires, $center->id, $now);
            }
            $this->cards->saveCards($issued);
            foreach ($issued as $card) {
                $this->cards->addEvent(GiftCardEvent::of((int) $card->id(), 'issued', $actor->name, $batch->code(), $now));
            }

            return new IssuedBatch($batch, $issued);
        });

        return $issued;
    }

    private function valueMinor(bool $fixed, int $minMinor, ?int $maxMinor, ?string $value): int
    {
        if ($fixed) {
            if ($value !== null && $value !== '') {
                throw new InvalidGiftCard('Una tarjeta de valor fijo emite siempre ese valor: no admite otro.');
            }

            return $minMinor;
        }
        if ($value === null || preg_match('/^\d{1,9}(\.\d{1,2})?$/', trim($value)) !== 1) {
            throw new InvalidGiftCard('Una tarjeta de valor variable necesita su valor (un importe con hasta dos decimales).');
        }
        $parts = explode('.', trim($value));
        $minor = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($minor < $minMinor || ($maxMinor !== null && $minor > $maxMinor)) {
            throw new InvalidGiftCard('El valor queda fuera del rango permitido del producto.');
        }

        return $minor;
    }

    private function expiry(?string $expiresAt, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if ($expiresAt === null || trim($expiresAt) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($expiresAt));
        if ($date === false || $date->format('Y-m-d') !== trim($expiresAt)) {
            throw new InvalidGiftCard('La caducidad debe ser una fecha válida (AAAA-MM-DD).');
        }
        if ($date->format('Y-m-d') <= $now->format('Y-m-d')) {
            throw new InvalidGiftCard('La caducidad debe ser posterior a hoy.');
        }

        return $date;
    }
}
