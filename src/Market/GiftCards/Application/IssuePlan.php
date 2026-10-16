<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Application;

use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\InvalidGiftCard;

/**
 * Qué se emite: una tarjeta individual con su propio valor, o un lote que recorre los valores de `min` a `max` de `step` en `step`
 * emitiendo `perValue` tarjetas de cada uno (con `min` = `max` es un solo valor y no hace falta paso). En un producto de valor fijo
 * el valor lo pone el producto: no se admite ni valor ni rango, solo la cantidad por valor.
 */
final readonly class IssuePlan
{
    private function __construct(
        public ?string $value,
        public ?string $min,
        public ?string $max,
        public ?string $step,
        public int $perValue,
        public bool $isRange,
    ) {
    }

    /** Una sola tarjeta; el valor solo se indica en productos de valor variable. */
    public static function single(?string $value): self
    {
        return new self($value, null, null, null, 1, false);
    }

    /** Un lote por rango. */
    public static function range(?string $min, ?string $max, ?string $step, int $perValue): self
    {
        return new self(null, $min, $max, $step, $perValue, true);
    }

    /**
     * Los valores a emitir, en unidades menores y en orden ascendente (cada uno se repite `perValue` veces al emitir).
     *
     * @return list<int>
     *
     * @throws InvalidGiftCard
     */
    public function denominations(GiftCardProduct $product): array
    {
        if ($this->perValue < 1) {
            throw new InvalidGiftCard('Hace falta al menos una tarjeta por valor.');
        }
        if ($product->fixed) {
            if ($this->isBlank($this->value) && $this->isBlank($this->min) && $this->isBlank($this->max) && $this->isBlank($this->step)) {
                return [$product->minMinor];
            }
            throw new InvalidGiftCard('Una tarjeta de valor fijo emite siempre ese valor: no admite otro ni un rango.');
        }

        if (!$this->isRange) {
            return [$this->inRange($this->toMinor($this->value, 'El valor'), $product)];
        }

        $min = $this->inRange($this->toMinor($this->min, 'El mínimo'), $product);
        $max = $this->inRange($this->toMinor($this->max, 'El máximo'), $product);
        if ($min > $max) {
            throw new InvalidGiftCard('El mínimo no puede ser mayor que el máximo.');
        }
        if ($min === $max) {
            $values = [$min];
        } else {
            $step = $this->toMinor($this->step, 'El paso');
            if ($step < 1) {
                throw new InvalidGiftCard('El paso debe ser mayor que cero.');
            }
            if ((int) floor(($max - $min) / $step) + 1 > GiftCardBatch::MAX_QUANTITY) {
                throw new InvalidGiftCard(sprintf('Con ese paso saldrían más de %d valores: sube el paso.', GiftCardBatch::MAX_QUANTITY));
            }
            $values = [];
            for ($v = $min; $v <= $max; $v += $step) {
                $values[] = $v;
            }
        }
        if (count($values) * $this->perValue > GiftCardBatch::MAX_QUANTITY) {
            throw new InvalidGiftCard(sprintf('Un lote lleva como mucho %d tarjetas: salen %d.', GiftCardBatch::MAX_QUANTITY, count($values) * $this->perValue));
        }

        return $values;
    }

    /** @return array{?int, ?int, ?int} mínimo, máximo y paso en unidades menores, para dejarlos en el lote */
    public function bounds(): array
    {
        if (!$this->isRange || $this->isBlank($this->min)) {
            return [null, null, null];
        }

        return [$this->toMinor($this->min, 'El mínimo'), $this->toMinor($this->max, 'El máximo'), $this->isBlank($this->step) ? null : $this->toMinor($this->step, 'El paso')];
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function toMinor(?string $value, string $what): int
    {
        if ($value === null || preg_match('/^\d{1,9}(\.\d{1,2})?$/', trim($value)) !== 1) {
            throw new InvalidGiftCard(sprintf('%s es obligatorio y debe ser un importe con hasta dos decimales.', $what));
        }
        $parts = explode('.', trim($value));

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function inRange(int $minor, GiftCardProduct $product): int
    {
        if ($minor < $product->minMinor || ($product->maxMinor !== null && $minor > $product->maxMinor)) {
            throw new InvalidGiftCard('El valor queda fuera del rango permitido del producto.');
        }

        return $minor;
    }
}
