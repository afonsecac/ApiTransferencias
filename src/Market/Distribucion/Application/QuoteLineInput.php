<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

/**
 * Una línea a cotizar: un producto por cantidad, o (para la vista previa) una carga suelta en kg o en L.
 */
final readonly class QuoteLineInput
{
    private function __construct(
        public ?int $productId,
        public ?int $quantity,
        public ?string $kg,
        public ?string $liters,
    ) {
    }

    public static function product(int $productId, int $quantity): self
    {
        return new self($productId, $quantity, null, null);
    }

    public static function kg(string $kg): self
    {
        return new self(null, null, $kg, null);
    }

    public static function liters(string $liters): self
    {
        return new self(null, null, null, $liters);
    }
}
