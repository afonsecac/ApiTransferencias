<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class StaffAlreadyActivated extends \DomainException
{
    public static function forUser(int $userId): self
    {
        return new self(sprintf('El usuario %d ya activó su cuenta: no hay invitación que reenviar.', $userId));
    }
}
