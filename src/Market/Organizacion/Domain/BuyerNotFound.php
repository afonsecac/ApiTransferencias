<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class BuyerNotFound extends \DomainException
{
    public static function forId(int $clientId): self
    {
        return new self(sprintf('No existe el comprador %d.', $clientId));
    }
}
