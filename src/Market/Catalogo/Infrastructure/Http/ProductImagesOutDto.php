<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

/**
 * La galería de un producto con los límites vigentes, para que la pantalla los muestre sin fijarlos en el código.
 */
class ProductImagesOutDto
{
    /**
     * @param list<ProductImageOutDto> $items en el orden de la galería
     */
    public function __construct(
        public array $items,
        public int $maxBytes,
        public int $maxPerProduct,
    ) {
    }
}
