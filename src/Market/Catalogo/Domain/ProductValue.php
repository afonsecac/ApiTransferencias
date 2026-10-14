<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El valor de un producto digital (una gift card propia, COMR-276), como el de las de DTOne: fijo, o variable entre un
 * mínimo y un máximo. Un producto lleva contenido (peso, volumen o unidades) o valor, no los dos: con valor es digital, sin
 * dimensiones ni entrega. Quien la recibe canjea un PIN con su número de serie; esa emisión y canje llegan con COMR-276.
 */
final readonly class ProductValue
{
    private function __construct(
        private string $currency,
        private Amount $min,
        private ?Amount $max,
    ) {
    }

    /**
     * @throws InvalidProduct
     */
    public static function fixed(string $currency, Amount $amount): self
    {
        return new self(self::currencyOf($currency), $amount, null);
    }

    /**
     * @throws InvalidProduct si el máximo no es mayor que el mínimo
     */
    public static function ranged(string $currency, Amount $min, Amount $max): self
    {
        if (!$min->isLessThan($max)) {
            throw new InvalidProduct('En una gift card de valor variable el máximo debe ser mayor que el mínimo.');
        }

        return new self(self::currencyOf($currency), $min, $max);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** El importe de una de valor fijo, o el mínimo de una de valor variable. */
    public function minAmount(): Amount
    {
        return $this->min;
    }

    /** El máximo de una de valor variable; null en una de valor fijo. */
    public function maxAmount(): ?Amount
    {
        return $this->max;
    }

    public function isFixed(): bool
    {
        return $this->max === null;
    }

    private static function currencyOf(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidProduct(sprintf('La moneda "%s" no es válida: tres letras (p. ej. USD).', $currency));
        }

        return $currency;
    }
}
