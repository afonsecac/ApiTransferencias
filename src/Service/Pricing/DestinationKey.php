<?php

namespace App\Service\Pricing;

/**
 * Normaliza la tupla (destinationAmount, destinationCurrency) para poder
 * compararla entre CommunicationPackage y CommunicationProduct — dos
 * entidades sin FK directa entre sí (el match es agnóstico de proveedor).
 * Nunca comparar los floats crudos por igualdad exacta: el mismo monto
 * puede llegar con distinto ruido de coma flotante según de dónde salga
 * (ej. CSQ aplicando `(valor/100)*exchangeRate`). Toda comparación pasa por
 * aquí, en PHP o como referencia del rango equivalente en SQL/DQL
 * (`BETWEEN :amount - DestinationKey::EPSILON AND :amount + DestinationKey::EPSILON`).
 */
final class DestinationKey
{
    /**
     * Tolerancia para comparar montos en SQL/DQL (BETWEEN amount±EPSILON).
     * 0.005 cubre el redondeo a 2 decimales que usa todo el dominio sin
     * llegar a confundir dos denominaciones reales distintas.
     */
    public const EPSILON = 0.005;

    private function __construct()
    {
        // Solo métodos estáticos — no instanciar.
    }

    /**
     * Clave de comparación normalizada: monto redondeado a 2 decimales +
     * moneda/unidad en mayúsculas. Dos tuplas con la misma clave se
     * consideran la misma denominación.
     */
    public static function of(float $amount, string $currency): string
    {
        return sprintf('%.2F|%s', round($amount, 2), strtoupper(trim($currency)));
    }

    public static function matches(float $amountA, string $currencyA, float $amountB, string $currencyB): bool
    {
        return self::of($amountA, $currencyA) === self::of($amountB, $currencyB);
    }
}
