<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwner;
use App\Market\Inventario\Domain\WarehouseOwners;
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
        private readonly WarehouseOwners $owners,
    ) {
    }

    /**
     * Invita a un usuario del dashboard a un almacén con unos permisos. Si el correo ya es usuario se vincula sin crear otra
     * cuenta; si no, se crea y queda «invitado» hasta que active su cuenta con el flujo de activación existente.
     *
     * Con `$external` se invita a una persona de un socio (COMR-288): solo en un almacén de socio, con el rol externo, y nunca
     * se convierte en externa a un usuario del dashboard. Una persona externa ya existente solo se vincula a almacenes de socio.
     *
     * @throws WarehouseNotFound
     * @throws InvalidWarehouseUser
     * @throws WarehouseUserAlreadyLinked
     */
    public function __invoke(int $warehouseId, string $email, ?string $firstName, ?string $lastName, WarehouseUserPermissions $permissions, ?int $inviterId, bool $external = false): WarehouseUserRow
    {
        $warehouse = $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $ofPartner = $warehouse->orgUnitId() !== null && $this->owners->find($warehouse->orgUnitId())?->regime() === WarehouseOwner::REGIME_PARTNER;
        if ($external && !$ofPartner) {
            throw new InvalidWarehouseUser('Las personas externas solo se invitan a almacenes de un socio.');
        }
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidWarehouseUser(sprintf('"%s" no es un correo válido.', $email));
        }
        if ($permissions->isEmpty()) {
            throw new InvalidWarehouseUser('El usuario necesita al menos un permiso en el almacén.');
        }

        $member = $this->staff->findByEmail($email);
        if ($member !== null && $external && !$member->external) {
            throw new InvalidWarehouseUser('Ese correo es de un usuario del dashboard: no se puede invitar como persona externa.');
        }
        if ($member !== null && $member->external && !$ofPartner) {
            throw new InvalidWarehouseUser('Es una persona externa de un socio: solo se vincula a almacenes de socio.');
        }
        if ($member === null) {
            $first = trim((string) $firstName);
            $last = trim((string) $lastName);
            if ($first === '' || $last === '') {
                throw new InvalidWarehouseUser('Para invitar a alguien nuevo hacen falta su nombre y apellido.');
            }
            $member = $this->staff->invite($email, $first, $last, $inviterId, $external);
        }
        if ($this->links->find($member->id, $warehouseId) !== null) {
            throw WarehouseUserAlreadyLinked::for($email);
        }

        $link = WarehouseUser::link($member->id, $warehouseId, $permissions);
        $this->links->save($link);

        return new WarehouseUserRow($link, $member);
    }
}
