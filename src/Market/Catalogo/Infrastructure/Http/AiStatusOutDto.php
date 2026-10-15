<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

/**
 * Respuesta de GET /market/ai-status (estructura de presentación, no es de entrada).
 */
class AiStatusOutDto
{
    public function __construct(public bool $enabled)
    {
    }
}
