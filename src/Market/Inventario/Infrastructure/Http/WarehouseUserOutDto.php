<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Market\Inventario\Application\WarehouseUserRow;

/**
 * Un usuario del almacén (estructura de presentación, no es de entrada). `status`: invited, active o inactive; `external`: persona
 * de un socio (COMR-288), no personal de la plataforma.
 */
class WarehouseUserOutDto
{
    /**
     * @param array{stock: bool, assembly: bool, transfers: bool, deliveries: bool} $permissions
     */
    public function __construct(
        public int $userId,
        public int $warehouseId,
        public ?string $email,
        public ?string $fullName,
        public array $permissions,
        public string $status,
        public bool $twoFactorEnabled,
        public bool $external,
        public string $createdAt,
    ) {
    }

    public static function from(WarehouseUserRow $row): self
    {
        $p = $row->link->permissions();

        return new self(
            $row->link->userId(),
            $row->link->warehouseId(),
            $row->member?->email,
            $row->member?->fullName,
            ['stock' => $p->stock, 'assembly' => $p->assembly, 'transfers' => $p->transfers, 'deliveries' => $p->deliveries],
            $row->status,
            $row->member->twoFactorEnabled ?? false,
            $row->member->external ?? false,
            $row->link->createdAt()->format(DATE_ATOM),
        );
    }
}
