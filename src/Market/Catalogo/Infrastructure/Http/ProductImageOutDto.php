<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Infrastructure\Storage\ImageUrls;

/**
 * Respuesta de los endpoints de la galería de un producto (estructura de presentación, no es de entrada). Las URLs de cada
 * tamaño (miniatura, listado, detalle) las construye ImageUrls: la API nunca devuelve la imagen.
 */
class ProductImageOutDto
{
    /**
     * @param array<string, string> $urls
     */
    public function __construct(
        public int $id,
        public int $position,
        public bool $isReference,
        public ?string $kind,
        public ?string $altEs,
        public ?string $altEn,
        public string $originalName,
        public string $mime,
        public int $bytes,
        public int $width,
        public int $height,
        public array $urls,
        public string $createdAt,
    ) {
    }

    public static function from(ProductImage $image, ImageUrls $urls): self
    {
        return new self(
            (int) $image->id(),
            $image->position(),
            $image->isReference(),
            $image->kind(),
            $image->altEs(),
            $image->altEn(),
            $image->originalName(),
            $image->mime(),
            $image->bytes(),
            $image->width(),
            $image->height(),
            $urls->for($image->file()),
            $image->createdAt()->format(DATE_ATOM),
        );
    }
}
