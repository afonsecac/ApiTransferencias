<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseUserAlreadyLinked extends \DomainException
{
    public static function for(string $email): self
    {
        return new self(sprintf('"%s" ya está vinculado a este almacén.', $email));
    }
}
