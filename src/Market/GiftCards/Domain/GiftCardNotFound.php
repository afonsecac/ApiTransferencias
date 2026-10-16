<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final class GiftCardNotFound extends \DomainException
{
    public static function serial(string $serial): self
    {
        return new self(sprintf('No existe la tarjeta %s.', $serial));
    }

    public static function pin(): self
    {
        return new self('No existe ninguna tarjeta con ese PIN.');
    }
}
