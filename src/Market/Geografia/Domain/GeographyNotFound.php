<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

final class GeographyNotFound extends \DomainException
{
    public static function province(int $id): self
    {
        return new self(sprintf('No existe la provincia %d.', $id));
    }

    public static function municipality(int $id): self
    {
        return new self(sprintf('No existe el municipio %d.', $id));
    }
}
