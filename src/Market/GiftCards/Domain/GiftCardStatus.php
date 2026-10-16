<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

/**
 * Estados de una tarjeta: emitida, activa (vendida), canjeada y anulada se guardan; «vencida» se deduce de la fecha de caducidad
 * mientras no esté canjeada ni anulada.
 */
enum GiftCardStatus: string
{
    case Issued = 'issued';
    case Active = 'active';
    case Redeemed = 'redeemed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'emitida',
            self::Active => 'activa',
            self::Redeemed => 'canjeada',
            self::Cancelled => 'anulada',
            self::Expired => 'vencida',
        };
    }
}
