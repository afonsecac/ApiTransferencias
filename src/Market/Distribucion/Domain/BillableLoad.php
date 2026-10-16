<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/**
 * Una carga tarifable: kilogramos (base de peso) o litros (base de volumen) con 6 decimales exactos, guardados como entero en
 * millonésimas. En una línea por peso con dimensiones lleva también el peso real y el volumétrico, de los que sale el tarifable.
 */
final readonly class BillableLoad
{
    public const WEIGHT = 'weight';
    public const VOLUME = 'volume';
    private const SCALE = 1_000_000;
    private const MAX_MICRO = 999_999_999_999_999;

    private function __construct(
        public string $kind,
        private int $micro,
        private ?int $realMicro,
        private ?int $volumetricMicro,
    ) {
    }

    public static function of(string $kind, string $value, ?string $real = null, ?string $volumetric = null): self
    {
        return self::fromMicro($kind, self::parse($value), $real === null ? null : self::parse($real), $volumetric === null ? null : self::parse($volumetric));
    }

    public static function fromMicro(string $kind, int $micro, ?int $realMicro = null, ?int $volumetricMicro = null): self
    {
        if (!in_array($kind, [self::WEIGHT, self::VOLUME], true)) {
            throw new InvalidQuote(sprintf('La base "%s" no es válida: peso o volumen.', $kind));
        }
        if ($micro < 0 || $micro > self::MAX_MICRO) {
            throw new InvalidQuote('La cantidad está fuera de rango.');
        }

        return new self($kind, $micro, $realMicro, $volumetricMicro);
    }

    public function micro(): int
    {
        return $this->micro;
    }

    public function value(): string
    {
        return self::format($this->micro);
    }

    public function realValue(): ?string
    {
        return $this->realMicro === null ? null : self::format($this->realMicro);
    }

    public function volumetricValue(): ?string
    {
        return $this->volumetricMicro === null ? null : self::format($this->volumetricMicro);
    }

    private static function parse(string $value): int
    {
        if (preg_match('/^(\d{1,9})(?:\.(\d{1,6}))?$/', trim($value), $m) !== 1) {
            throw new InvalidQuote(sprintf('La cantidad "%s" no es válida: un número no negativo con hasta 6 decimales.', $value));
        }

        return (int) $m[1] * self::SCALE + (int) str_pad($m[2] ?? '', 6, '0');
    }

    private static function format(int $micro): string
    {
        return sprintf('%d.%06d', intdiv($micro, self::SCALE), $micro % self::SCALE);
    }
}
