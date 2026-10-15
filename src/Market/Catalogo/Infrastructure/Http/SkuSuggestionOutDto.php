<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

/**
 * Respuesta de GET /market/products/sku-suggestion (estructura de presentación, no es de entrada).
 */
class SkuSuggestionOutDto
{
    public function __construct(public string $sku)
    {
    }
}
