<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Código único e inmutable del producto (COMR-173): mayúsculas, números y . _ - (máx. 64). El consumidor identifica el
 * producto por su id y recibe el sku.
 */
final readonly class Sku
{
    public const PATTERN = '/^[A-Z0-9][A-Z0-9._-]{0,63}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidProduct(sprintf('El sku "%s" no es válido: letras, números y . _ - (máximo 64), sin espacios.', $value));
        }
        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
