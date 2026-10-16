<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Http;

use App\Market\GiftCards\Application\GiftCardDetails;
use App\Market\GiftCards\Application\GiftCardRow;
use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardStatus;

/**
 * Una tarjeta (estructura de presentación, no es de entrada). El PIN NUNCA va aquí: se pide aparte y deja registro.
 */
class GiftCardOutDto
{
    /**
     * @param array{id: int, name: string}|null $product
     * @param list<array{type: string, actor: string, detail: string|null, at: string}>|null $events
     */
    public function __construct(
        public string $serial,
        public int $batchId,
        public ?string $batchCode,
        public ?array $product,
        public string $value,
        public string $currency,
        public string $status,
        public ?string $expiresAt,
        public int $costCenterId,
        public ?string $costCenterCode,
        public ?string $soldTo,
        public ?string $saleRef,
        public ?string $redeemedAt,
        public ?string $redemptionRef,
        public ?string $cancelReason,
        public string $createdAt,
        public ?array $events = null,
    ) {
    }

    public static function fromRow(GiftCardRow $row): self
    {
        return self::build($row->card, $row->status, $row->product?->id, $row->product?->name, null, null, null);
    }

    public static function fromDetails(GiftCardDetails $d): self
    {
        $events = array_map(static fn ($e) => ['type' => $e->type(), 'actor' => $e->actorName(), 'detail' => $e->detail(), 'at' => $e->createdAt()->format(DATE_ATOM)], $d->events);

        $center = null;
        foreach ($d->product->costCenters ?? [] as $candidate) {
            if ($candidate->id === $d->card->costCenterId()) {
                $center = $candidate->code;
            }
        }

        return self::build($d->card, $d->status, $d->product?->id, $d->product?->name, $d->batch?->code(), $events, $center);
    }

    /**
     * @param list<array{type: string, actor: string, detail: string|null, at: string}>|null $events
     */
    private static function build(GiftCard $c, GiftCardStatus $status, ?int $productId, ?string $productName, ?string $batchCode, ?array $events, ?string $costCenterCode): self
    {
        return new self(
            $c->serial(),
            $c->batchId(),
            $batchCode,
            $productId === null ? null : ['id' => $productId, 'name' => (string) $productName],
            sprintf('%d.%02d', intdiv($c->valueMinor(), 100), $c->valueMinor() % 100),
            $c->currency(),
            $status->value,
            $c->expiresAt()?->format('Y-m-d'),
            $c->costCenterId(),
            $costCenterCode,
            $c->soldTo(),
            $c->saleRef(),
            $c->redeemedAt()?->format(DATE_ATOM),
            $c->redemptionRef(),
            $c->cancelReason(),
            $c->createdAt()->format(DATE_ATOM),
            $events,
        );
    }
}
