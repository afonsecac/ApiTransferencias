<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Una cantidad positiva con su unidad. Se guarda en la unidad base del tipo (por defecto kilogramos, litros o unidades;
 * MeasureSystem), redondeada a 6 decimales, y la unidad y el valor originales se conservan aparte, solo para mostrarla y editarla
 * tal como el vendedor la introdujo (p. ej. 2,5 lb). Los cálculos y las tarifas operan siempre sobre la base.
 */
final readonly class Quantity
{
    private const BASE_DECIMALS = 6;

    private function __construct(
        private float $base,
        private float $original,
        private MeasureUnit $unit,
    ) {
    }

    /**
     * @param MeasureSystem|null $system las unidades base; sin él, las estándar (kilogramos y litros)
     *
     * @throws InvalidProduct si no es una cantidad positiva (o es una fracción de unidades)
     */
    public static function of(float $value, MeasureUnit $unit, ?MeasureSystem $system = null): self
    {
        if (!is_finite($value) || $value <= 0) {
            throw new InvalidProduct('La cantidad debe ser un número mayor que cero.');
        }
        if ($unit === MeasureUnit::UNIT && floor($value) !== $value) {
            throw new InvalidProduct('Las unidades son un número entero.');
        }
        $base = round($value * ($system ?? MeasureSystem::standard())->factorOf($unit), self::BASE_DECIMALS);
        if ($base <= 0) {
            throw new InvalidProduct('La cantidad es demasiado pequeña.');
        }

        return new self($base, $value, $unit);
    }

    /** Reconstruye lo que ya se guardó, sin volver a calcular. */
    public static function fromStored(float $base, float $original, MeasureUnit $unit): self
    {
        return new self($base, $original, $unit);
    }

    public function baseValue(): float
    {
        return $this->base;
    }

    public function originalValue(): float
    {
        return $this->original;
    }

    public function unit(): MeasureUnit
    {
        return $this->unit;
    }

    public function kind(): MeasureKind
    {
        return $this->unit->kind();
    }

    /**
     * La cantidad expresada en otra unidad del mismo tipo.
     *
     * @param MeasureSystem|null $system el sistema con que se guardó; sin él, el estándar
     *
     * @throws InvalidProduct si la unidad es de otro tipo
     */
    public function in(MeasureUnit $other, ?MeasureSystem $system = null): float
    {
        if ($other->kind() !== $this->kind()) {
            throw new InvalidProduct(sprintf('No se puede convertir %s a %s: son de distinto tipo.', $this->unit->value, $other->value));
        }

        return round($this->base / ($system ?? MeasureSystem::standard())->factorOf($other), 4);
    }
}
