<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class UnknownBuyerCountry extends \DomainException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('El país "%s" del comprador no es un código ISO-3166 alfa-3 conocido.', $code));
    }
}
