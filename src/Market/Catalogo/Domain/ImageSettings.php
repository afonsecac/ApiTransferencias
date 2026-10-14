<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Los límites de las imágenes, configurables por administración (no son constantes del código).
 */
interface ImageSettings
{
    /** Bytes máximos por imagen. */
    public function maxBytes(): int;

    /** Imágenes máximas por producto. */
    public function maxPerProduct(): int;
}
