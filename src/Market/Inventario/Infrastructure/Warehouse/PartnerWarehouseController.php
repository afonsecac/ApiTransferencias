<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Warehouse;

use App\Entity\User;
use App\Market\Inventario\Application\ListStock;
use App\Market\Inventario\Application\MyWarehouse;
use App\Market\Inventario\Application\StockLineDetails;
use App\Market\Inventario\Application\WarehouseAccess;
use App\Market\Inventario\Infrastructure\Http\StockLineOutDto;
use App\Market\Inventario\Infrastructure\Security\WarehouseVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lo que ve y hace una persona de un socio de almacén en SU almacén (COMR-288). Vive bajo el prefijo propio `/warehouse/api`,
 * fuera del dashboard: el rol externo no entra en `/dashboard/api`. Cada ruta con un id de almacén pasa por el Voter, que
 * concede solo con un vínculo activo con ESE almacén y el permiso correspondiente (un permiso en el A nunca vale en el B).
 * Nunca devuelve precios de cliente, costos de otros almacenes ni datos de los compradores.
 */
#[AsController]
#[Route('', name: 'warehouse_partner_')]
final class PartnerWarehouseController
{
    public function __construct(
        private readonly WarehouseAccess $access,
        private readonly ListStock $listStock,
        private readonly Security $security,
    ) {
    }

    #[Route('/me/warehouses', name: 'mine', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        $user = $this->security->getUser();

        return new JsonResponse($user instanceof User ? array_map(
            static fn (MyWarehouse $w): array => [
                'id' => (int) $w->warehouse->id(),
                'slug' => $w->warehouse->slug()->value(),
                'name' => $w->warehouse->name(),
                'status' => $w->warehouse->status()->value,
                'permissions' => [
                    'stock' => $w->permissions->stock,
                    'assembly' => $w->permissions->assembly,
                    'transfers' => $w->permissions->transfers,
                    'deliveries' => $w->permissions->deliveries,
                ],
            ],
            $this->access->warehousesOf((int) $user->getId()),
        ) : []);
    }

    #[Route('/warehouses/{id}/stock', name: 'stock', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(WarehouseVoter::STOCK, subject: 'id')]
    public function stock(int $id, Request $request): JsonResponse
    {
        $q = $request->query->get('q');
        $type = $request->query->get('type');

        return new JsonResponse(array_map(
            static fn (StockLineDetails $d) => StockLineOutDto::from($d),
            ($this->listStock)(
                $id,
                is_string($q) ? $q : null,
                is_string($type) && in_array($type, ['product', 'combo'], true) ? $type : null,
                in_array($request->query->get('low'), ['1', 'true'], true),
            ),
        ));
    }
}
