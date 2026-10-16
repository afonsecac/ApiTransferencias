<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Puerto síncrono de Organización hacia los contextos que usan sus unidades: qué depende de ellas, para no dejarlas en un estado
 * inválido en silencio. Hoy, los almacenes que tiene una unidad (contexto Inventario).
 */
interface OrgUnitUsage
{
    public function warehouseCount(int $orgUnitId): int;
}
