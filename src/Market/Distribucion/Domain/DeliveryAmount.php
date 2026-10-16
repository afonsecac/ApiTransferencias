<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * Un importe de entrega no negativo con hasta 4 decimales, guardado como entero en diezmilésimas: exacto y sin coma flotante.
 */
final readonly class DeliveryAmount
{
    private const SCALE = 10000;
    private const MAX_UNITS = 999999999999; // 99.999.999,9999

    private function __construct(private int $units)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,8})(?:\.(\d{1,4}))?$/', $value, $m) !== 1) {
            throw new InvalidCoverage(sprintf('El importe "%s" no es válido: un número no negativo con hasta 4 decimales.', $value));
        }
        $units = (int) $m[1] * self::SCALE + (int) str_pad($m[2] ?? '', 4, '0');
        if ($units > self::MAX_UNITS) {
            throw new InvalidCoverage('El importe es demasiado grande.');
        }

        return new self($units);
    }

    public function units(): int
    {
        return $this->units;
    }

    public function value(): string
    {
        return sprintf('%d.%04d', intdiv($this->units, self::SCALE), $this->units % self::SCALE);
    }

    public function isZero(): bool
    {
        return $this->units === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->units > $other->units;
    }
}
