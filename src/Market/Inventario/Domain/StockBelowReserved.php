<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class StockBelowReserved extends \DomainException
{
    public static function of(int $onHand, int $delta, int $reserved): self
    {
        return new self(sprintf('El ajuste dejaría %d en mano, por debajo de lo reservado (%d): hay %d en mano.', $onHand + $delta, $reserved, $onHand));
    }
}
