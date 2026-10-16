<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final class GiftCardAlreadyRedeemed extends \DomainException
{
    public static function serial(string $serial): self
    {
        return new self(sprintf('La tarjeta %s ya fue canjeada: no se puede canjear dos veces.', $serial));
    }
}
