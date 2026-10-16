<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class CoverageWarehouseInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el almacén %d.', $id));
    }

    public static function draft(string $name): self
    {
        return new self(sprintf('El almacén "%s" está en borrador: no admite cobertura hasta que se active.', $name));
    }

    public static function notActive(string $name): self
    {
        return new self(sprintf('El almacén "%s" no está activo: solo un almacén activo puede empezar a cubrir un municipio.', $name));
    }
}
