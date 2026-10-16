<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final class GiftCardTransitionInvalid extends \DomainException
{
    public static function between(string $serial, GiftCardStatus $status, string $action): self
    {
        return new self(sprintf('La tarjeta %s está %s: no se puede %s.', $serial, $status->label(), $action));
    }
}
