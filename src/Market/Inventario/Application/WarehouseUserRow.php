<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StaffMember;
use App\Market\Inventario\Domain\WarehouseUser;

/** Un usuario del almacén con su estado: invitado (sin activar), activo o inactivo (vínculo desactivado). */
final readonly class WarehouseUserRow
{
    public string $status;

    public function __construct(public WarehouseUser $link, public ?StaffMember $member)
    {
        $this->status = !$link->isActive() ? 'inactive' : ($member !== null && !$member->activated ? 'invited' : 'active');
    }
}
