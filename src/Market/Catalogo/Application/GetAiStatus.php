<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\AiSettings;

/**
 * Si la asistencia con IA está habilitada para el catálogo (COMR-277): el formulario de producto la usará para ofrecer o no la
 * creación automática. Apagada por defecto.
 */
final class GetAiStatus
{
    public function __construct(private readonly AiSettings $settings)
    {
    }

    public function __invoke(): bool
    {
        return $this->settings->isEnabled();
    }
}
