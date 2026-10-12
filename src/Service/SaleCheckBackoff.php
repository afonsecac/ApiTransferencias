<?php

namespace App\Service;

/**
 * Espera entre consultas de estado de una venta pendiente (COMR-260): crece con el número de consultas
 * ya hechas hasta un tope de 1 h, para que una venta que tarda no genere consultas sin fin cada pocos segundos.
 */
final class SaleCheckBackoff
{
    /** Segundos de espera tras 0, 1, 2… consultas; a partir de la última se repite. */
    private const DELAYS_SECONDS = [5, 15, 60, 300, 900, 3600];

    private function __construct()
    {
    }

    public static function delaySeconds(int $attempts): int
    {
        $index = max(0, min($attempts, \count(self::DELAYS_SECONDS) - 1));

        return self::DELAYS_SECONDS[$index];
    }

    public static function nextCheckAt(int $attempts, \DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(sprintf('+%d seconds', self::delaySeconds($attempts)));
    }
}
