<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

final class PlatformSellerProtected extends \DomainException
{
    public static function cannotBeDeactivated(): self
    {
        return new self('El vendedor de plataforma está protegido: no se puede desactivar ni borrar.');
    }
}
