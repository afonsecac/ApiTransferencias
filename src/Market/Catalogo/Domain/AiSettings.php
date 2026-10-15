<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El interruptor «IA habilitada» del catálogo (COMR-277). Hoy solo se consulta: la creación de productos con IA, y con ella
 * el proveedor, los datos que se envían y los permisos por cuenta, son una historia aparte.
 */
interface AiSettings
{
    public function isEnabled(): bool;
}
