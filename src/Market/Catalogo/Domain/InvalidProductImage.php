<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Los datos de una imagen de producto no son válidos (formato, tamaño, textos).
 */
final class InvalidProductImage extends \DomainException
{
    public static function unsupportedMime(string $mime): self
    {
        return new self(sprintf('El formato "%s" no está permitido: usa JPEG, PNG o WebP.', $mime));
    }

    public static function tooBig(int $maxBytes): self
    {
        $limit = $maxBytes >= 1_000_000
            ? rtrim(rtrim(number_format($maxBytes / 1_000_000, 1, '.', ''), '0'), '.') . ' MB'
            : max(1, intdiv($maxBytes, 1000)) . ' KB';

        return new self(sprintf('La imagen supera el máximo de %s.', $limit));
    }

    public static function tooManyPixels(int $maxPixels): self
    {
        return new self(sprintf('La imagen tiene demasiados píxeles (máximo %d millones).', intdiv($maxPixels, 1_000_000)));
    }

    public static function empty(): self
    {
        return new self('La imagen está vacía o no tiene dimensiones.');
    }

    public static function tooLong(string $field, int $max): self
    {
        return new self(sprintf('%s admite como máximo %d caracteres.', $field, $max));
    }

    public static function badPosition(): self
    {
        return new self('La posición de una imagen empieza en 1.');
    }
}
