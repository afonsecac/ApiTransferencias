<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * La fórmula del precio de entrega (COMR-73), en aritmética entera exacta (nunca coma flotante):
 *
 *   importe de cada línea = redondear2(carga × tarifa)      (HALF_UP; kg por la tarifa por kg, L por la tarifa por L)
 *   S = suma de los importes ya redondeados
 *   precio = mínimo si S < mínimo; máximo si hay máximo y S > máximo; S en otro caso
 *
 * El mínimo y el máximo se aplican UNA vez sobre la suma, por entrega. Si tienen más de 2 decimales se redondean a céntimos.
 */
final class DeliveryPricing
{
    /**
     * @param list<BillableLoad> $loads
     */
    public static function price(DeliveryRates $rates, array $loads): PriceBreakdown
    {
        $lines = [];
        $kg = $liters = 0;
        foreach ($loads as $load) {
            $rate = $load->kind === BillableLoad::WEIGHT ? $rates->perKg : $rates->perL;
            $cents = self::cents($load->micro(), $rate->units());
            $lines[] = new PricedLine($load, $rate, $cents);
            $load->kind === BillableLoad::WEIGHT ? $kg += $cents : $liters += $cents;
        }
        $subtotal = $kg + $liters;
        $min = self::roundUnits($rates->min->units());
        $max = $rates->max === null ? null : self::roundUnits($rates->max->units());

        [$total, $limit] = match (true) {
            $subtotal < $min => [$min, 'min'],
            $max !== null && $subtotal > $max => [$max, 'max'],
            default => [$subtotal, 'none'],
        };

        return new PriceBreakdown($lines, $kg, $liters, $subtotal, $limit, $total);
    }

    public static function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /** carga (millonésimas) × tarifa (diezmilésimas) → céntimos redondeados HALF_UP, sin desbordar el entero. */
    private static function cents(int $micro, int $rateUnits): int
    {
        // micro × tarifa tiene 10 decimales y un céntimo son 8: se parte cada factor en miles para no pasar de 64 bits.
        $a1 = intdiv($micro, 10_000);
        $a0 = $micro % 10_000;
        $b1 = intdiv($rateUnits, 10_000);
        $b0 = $rateUnits % 10_000;
        // Los factores ya están acotados (carga y tarifa), pero una carga enorme por una tarifa enorme se rechaza antes de desbordar.
        if ((float) $a1 * (float) $b1 > 4.0e18 || ((float) $a1 * $b0 + (float) $a0 * $b1) * 10_000 > 4.0e18) {
            throw new InvalidQuote('El importe es demasiado grande.');
        }

        return $a1 * $b1 + intdiv(($a1 * $b0 + $a0 * $b1) * 10_000 + $a0 * $b0 + 50_000_000, 100_000_000);
    }

    /** diezmilésimas → céntimos, HALF_UP */
    private static function roundUnits(int $units): int
    {
        return intdiv($units + 50, 100);
    }
}
