<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El orden pedido no es una permutación de las imágenes de la galería (falta alguna, sobra alguna o se repite).
 */
final class InvalidGalleryOrder extends \DomainException
{
    public static function notAPermutation(): self
    {
        return new self('El orden debe incluir exactamente las imágenes de la galería, una vez cada una.');
    }
}
