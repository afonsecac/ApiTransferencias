<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class CoverageNotFound extends \DomainException
{
    public static function for(int $warehouseId, int $municipalityId): self
    {
        return new self(sprintf('El almacén %d no tiene cobertura en el municipio %d.', $warehouseId, $municipalityId));
    }
}
