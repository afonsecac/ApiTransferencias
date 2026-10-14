<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Lo que hace de un producto una gift card propia (COMR-276), como las de DTOne: digital (sin peso, contenido,
 * dimensiones ni entrega), de valor fijo o variable entre un mínimo y un máximo, y de una categoría. Quien la recibe
 * canjea un PIN con su número de serie; esa emisión y canje llegan con COMR-276.
 */
final readonly class GiftCard
{
    private function __construct(
        private GiftCardCategory $category,
        private string $currency,
        private Amount $min,
        private ?Amount $max,
    ) {
    }

    /**
     * @throws InvalidProduct
     */
    public static function fixed(GiftCardCategory $category, string $currency, Amount $amount): self
    {
        return new self($category, self::currencyOf($currency), $amount, null);
    }

    /**
     * @throws InvalidProduct si el máximo no es mayor que el mínimo
     */
    public static function ranged(GiftCardCategory $category, string $currency, Amount $min, Amount $max): self
    {
        if (!$min->isLessThan($max)) {
            throw new InvalidProduct('En una gift card de valor variable el máximo debe ser mayor que el mínimo.');
        }

        return new self($category, self::currencyOf($currency), $min, $max);
    }

    public function category(): GiftCardCategory
    {
        return $this->category;
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
