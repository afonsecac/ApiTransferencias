<?php

declare(strict_types=1);

namespace App\Market\Otro\Application;

use App\Market\Violation\Infrastructure\Repositorio;

/** Fixture: un contexto no puede importar la Infrastructure de otro. */
final class LlamaAOtroContexto
{
    public function __construct(public readonly Repositorio $repositorio)
    {
    }
}
