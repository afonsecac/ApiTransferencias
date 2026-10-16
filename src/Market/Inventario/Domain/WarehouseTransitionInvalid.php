<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseTransitionInvalid extends \DomainException
{
    public static function between(WarehouseStatus $from, string $action): self
    {
        return new self(sprintf('Un almacén %s no se puede %s.', self::label($from), $action));
    }

    private static function label(WarehouseStatus $status): string
    {
        return match ($status) {
            WarehouseStatus::Draft => 'en borrador',
            WarehouseStatus::Active => 'activo',
            WarehouseStatus::Inactive => 'inactivo',
        };
    }
}
