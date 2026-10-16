<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Un usuario del dashboard tal como lo ve este contexto: `activated` es falso hasta que activa su cuenta (invitado); `external` es una persona de un socio, no del personal de la plataforma (COMR-288). */
final readonly class StaffMember
{
    public function __construct(
        public int $id,
        public string $email,
        public string $fullName,
        public bool $activated,
        public bool $twoFactorEnabled,
        public bool $external = false,
    ) {
    }
}
