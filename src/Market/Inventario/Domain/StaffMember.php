<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Un usuario del dashboard tal como lo ve este contexto: `activated` es falso hasta que activa su cuenta (invitado). */
final readonly class StaffMember
{
    public function __construct(
        public int $id,
        public string $email,
        public string $fullName,
        public bool $activated,
        public bool $twoFactorEnabled,
    ) {
    }
}
