<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Puerto hacia los usuarios del dashboard: busca, crea con invitación y reenvía la invitación. */
interface WarehouseStaff
{
    public function findByEmail(string $email): ?StaffMember;

    /**
     * @param list<int> $ids
     *
     * @return array<int, StaffMember> por id
     */
    public function findMany(array $ids): array;

    /**
     * Crea el usuario y lanza el correo de activación existente (la contraseña no se fija aquí). Con `$external` es una persona de
     * un socio de almacén y recibe el rol externo, no uno del dashboard (COMR-288).
     */
    public function invite(string $email, string $firstName, string $lastName, ?int $inviterId, bool $external = false): StaffMember;

    /** @throws StaffAlreadyActivated si ya activó su cuenta */
    public function resendInvitation(int $userId): void;
}
