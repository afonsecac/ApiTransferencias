<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class SellerNotFound extends \DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('No existe el vendedor %d.', $id));
    }

    public static function platform(): self
    {
        return new self('No existe el vendedor de plataforma.');
    }
}
