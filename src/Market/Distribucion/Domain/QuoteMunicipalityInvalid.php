<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

final class QuoteMunicipalityInvalid extends \DomainException
{
    public static function unknown(int $id): self
    {
        return new self(sprintf('No existe el municipio %d.', $id));
    }

    public static function notDeliverable(string $name): self
    {
        return new self(sprintf('El municipio "%s" está inactivo: no es entregable.', $name));
    }
}
