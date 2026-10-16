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

    /** Crea el usuario del dashboard y lanza el correo de activación existente (la contraseña no se fija aquí). */
    public function invite(string $email, string $firstName, string $lastName, ?int $inviterId): StaffMember;

    /** @throws StaffAlreadyActivated si ya activó su cuenta */
    public function resendInvitation(int $userId): void;
}
