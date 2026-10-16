<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

final class OrgUnitTypeInUse extends \DomainException
{
    public static function warehouses(int $count): self
    {
        return new self(sprintf('La unidad es dueña de %d almacén(es): no puede dejar de ser plataforma o socio de almacén. Reasigna o descarta primero esos almacenes.', $count));
    }
}
