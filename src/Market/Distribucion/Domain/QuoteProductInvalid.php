<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class QuoteProductInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el producto %d.', $id));
    }

    public static function notDeliverable(string $name): self
    {
        return new self(sprintf('El producto "%s" no tiene una base de entrega calculable (es digital o le falta el peso o el volumen).', $name));
    }
}
