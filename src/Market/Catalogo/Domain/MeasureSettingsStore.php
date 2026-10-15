<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Dónde se guardan las medidas del catálogo (COMR-281): el puerto de escritura de MeasureSettings. Quién lo implementa es la
 * infraestructura. Las reglas (tipo de cada unidad, bloqueo con productos) las aplica el caso de uso, no el almacén.
 */
interface MeasureSettingsStore
{
    public function save(MeasureUnit $weightBase, MeasureUnit $volumeBase, int $volumetricDivisor): void;
}
