<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

final class GiftCardProductInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el producto %d.', $id));
    }

    public static function notAGiftCard(string $name): self
    {
        return new self(sprintf('El producto "%s" no es digital con un valor: no se pueden emitir tarjetas de él.', $name));
    }
}
