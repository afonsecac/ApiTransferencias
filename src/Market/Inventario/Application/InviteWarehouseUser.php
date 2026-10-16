<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserAlreadyLinked;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class InviteWarehouseUser
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseUserRepository $links,
        private readonly WarehouseStaff $staff,
    ) {
    }

    /**
     * Invita a un usuario del dashboard a un almacén con unos permisos. Si el correo ya es usuario se vincula sin crear otra
     * cuenta; si no, se crea y queda «invitado» hasta que active su cuenta con el flujo de activación existente.
     *
     * @throws WarehouseNotFound
     * @throws InvalidWarehouseUser
     * @throws WarehouseUserAlreadyLinked
     */
    public function __invoke(int $warehouseId, string $email, ?string $firstName, ?string $lastName, WarehouseUserPermissions $permissions, ?int $inviterId): WarehouseUserRow
    {
        $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidWarehouseUser(sprintf('"%s" no es un correo válido.', $email));
        }
        if ($permissions->isEmpty()) {
            throw new InvalidWarehouseUser('El usuario necesita al menos un permiso en el almacén.');
        }

        $member = $this->staff->findByEmail($email);
        if ($member === null) {
            $first = trim((string) $firstName);
            $last = trim((string) $lastName);
            if ($first === '' || $last === '') {
                throw new InvalidWarehouseUser('Para invitar a alguien nuevo hacen falta su nombre y apellido.');
            }
            $member = $this->staff->invite($email, $first, $last, $inviterId);
        }
        if ($this->links->find($member->id, $warehouseId) !== null) {
            throw WarehouseUserAlreadyLinked::for($email);
        }

        $link = WarehouseUser::link($member->id, $warehouseId, $permissions);
        $this->links->save($link);

        return new WarehouseUserRow($link, $member);
    }
}
