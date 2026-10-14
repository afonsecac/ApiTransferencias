<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El resultado de guardar una imagen: la huella con la que se nombran sus tamaños y lo que se leyó del archivo.
 */
final readonly class StoredImage
{
    public function __construct(public string $file, public string $mime, public int $bytes, public int $width, public int $height)
    {
    }
}
