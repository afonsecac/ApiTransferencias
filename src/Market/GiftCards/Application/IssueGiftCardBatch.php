<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardActor;
use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardNumber;
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
     * Emite tarjetas de un producto digital con valor, según el plan: una individual o un lote por rango (de `min` a `max` de `step`
     * en `step`, con las tarjetas que se pidan de cada valor). Serie consecutiva, PIN no adivinable (cifrado y con huella) y el
     * centro de costo al que se imputa la emisión (por defecto el principal del vendedor del producto). Todo se guarda o nada.
     *
     * @param string|null $expiresAt fecha `Y-m-d` posterior a hoy, o nada si no caduca
     *
     * @throws GiftCardProductInvalid
     * @throws InvalidGiftCard
     */
    public function __invoke(int $productId, IssuePlan $plan, ?string $expiresAt, ?int $costCenterId, GiftCardActor $actor, ?\DateTimeImmutable $now = null): IssuedBatch
    {
        $now ??= new \DateTimeImmutable();
        $product = $this->products->find($productId) ?? throw GiftCardProductInvalid::unknown($productId);
        if (!$product->digital || $product->currency === '') {
            throw GiftCardProductInvalid::notAGiftCard($product->name);
        }

        $values = [];
        foreach ($plan->denominations($product) as $denomination) {
            for ($i = 0; $i < $plan->perValue; ++$i) {
                $values[] = $denomination;
            }
        }
        $quantity = count($values);
        if ($quantity < 1 || $quantity > GiftCardBatch::MAX_QUANTITY) {
            throw new InvalidGiftCard(sprintf('Un lote lleva entre 1 y %d tarjetas.', GiftCardBatch::MAX_QUANTITY));
        }
        $expires = $this->expiry($expiresAt, $now);
        $center = $costCenterId === null ? $product->principalCostCenter() : $product->activeCostCenter($costCenterId);
        if ($center === null) {
            throw new InvalidGiftCard('El centro de costo debe ser un centro activo del vendedor del producto.');
        }
        [$min, $max, $step] = $plan->bounds();

        /** @var IssuedBatch $issued */
        $issued = $this->cards->transactional(function () use ($product, $values, $quantity, $min, $max, $step, $expires, $center, $actor, $now): IssuedBatch {
            $batch = GiftCardBatch::open($product->id, $quantity, $expires, $center->id, $actor->name, $now, $min, $max, $step);
            $this->cards->saveBatch($batch);

            $issued = [];
            foreach ($this->freshNumbers($quantity) as $i => $number) {
                $pin = $this->vault->generate();
                $issued[] = GiftCard::issue((int) $batch->id(), $product->id, $number, $values[$i], $product->currency, $this->vault->encrypt($pin), $this->vault->hash($pin), $expires, $center->id, $now);
            }
            $this->cards->saveCards($issued);
            foreach ($issued as $card) {
                $this->cards->addEvent(GiftCardEvent::of((int) $card->id(), 'issued', $actor->name, $batch->code(), $now));
            }

            return new IssuedBatch($batch, $issued);
        });

        return $issued;
    }

    /**
     * Números de tarjeta aleatorios (con su dígito de control) que no existen ya ni se repiten dentro del lote.
     *
     * @return list<string>
     */
    private function freshNumbers(int $quantity): array
    {
        $numbers = [];
        while (count($numbers) < $quantity) {
            $candidates = [];
            for ($i = count($numbers); $i < $quantity; ++$i) {
                $candidates[] = GiftCardNumber::generate();
            }
            $candidates = array_values(array_unique($candidates));
            $free = array_values(array_diff($candidates, $numbers, $this->cards->takenSerials($candidates)));
            $numbers = [...$numbers, ...$free];
        }

        return array_slice($numbers, 0, $quantity);
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
