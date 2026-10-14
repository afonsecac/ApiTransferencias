<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Un importe positivo con dos decimales (COMR-101): se guarda y compara en unidades menores (enteros), nunca en float.
 */
final readonly class Amount
{
    private const PATTERN = '/^\d{1,12}(\.\d{1,2})?$/';

    private function __construct(private int $minorUnits)
    {
    }

    /**
     * @throws InvalidProduct si no es un número positivo de hasta dos decimales
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidProduct(sprintf('El importe "%s" no es válido: un número positivo con hasta dos decimales (punto decimal).', $value));
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($minor <= 0) {
            throw new InvalidProduct('El importe debe ser mayor que cero.');
        }

        return new self($minor);
    }

    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    /** Siempre con dos decimales: «25.00». */
    public function toString(): string
    {
        return sprintf('%d.%02d', intdiv($this->minorUnits, 100), $this->minorUnits % 100);
    }

    public function isLessThan(self $other): bool
    {
        return $this->minorUnits < $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }
}
