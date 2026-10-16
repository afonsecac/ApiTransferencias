<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class MainWarehouseProtected extends \DomainException
{
    public static function cannotBeDeactivated(): self
    {
        return new self('El almacén principal de la plataforma no se puede desactivar.');
    }

    public static function cannotChangeOwner(): self
    {
        return new self('El almacén principal de la plataforma no cambia de dueño.');
    }

    public static function cannotBeDiscarded(): self
    {
        return new self('El almacén principal de la plataforma no se puede descartar.');
    }
}
