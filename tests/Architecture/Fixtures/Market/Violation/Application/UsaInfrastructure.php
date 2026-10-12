<?php

declare(strict_types=1);

namespace App\Market\Violation\Application;

use App\Market\Violation\Infrastructure\Repositorio;

/** Fixture: Application no puede depender de Infrastructure. */
final class UsaInfrastructure
{
    public function __construct(public readonly Repositorio $repositorio)
    {
    }
}
