<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class QuoteWarehouseNotActive extends \DomainException
{
    public static function in(string $municipality): self
    {
        return new self(sprintf('Los almacenes que cubren "%s" no están activos: solo un almacén activo cotiza entrega.', $municipality));
    }
}
