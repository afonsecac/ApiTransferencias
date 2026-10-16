<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Market\Inventario\Application\ActivateWarehouse;
use App\Market\Inventario\Application\CreateWarehouse;
use App\Market\Inventario\Application\DeactivateWarehouse;
use App\Market\Inventario\Application\DiscardWarehouse;
use App\Market\Inventario\Application\GetWarehouse;
use App\Market\Inventario\Application\GetWarehouseDefaults;
use App\Market\Inventario\Application\ListWarehouses;
use App\Market\Inventario\Application\UpdateWarehouse;
use App\Market\Inventario\Application\WarehouseDetails;
use App\Market\Inventario\Application\WarehouseInput;
use App\Market\Inventario\Domain\InvalidWarehouse;
use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\PartnerPermissions;
use App\Market\Inventario\Domain\WarehouseAlreadyExists;
use App\Market\Inventario\Domain\WarehouseIncomplete;
use App\Market\Inventario\Domain\WarehouseMunicipalityInvalid;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwnerInvalid;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Shared\Domain\InvalidSlug;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Almacenes del mercado (COMR-285). El controlador solo traduce HTTP: el DTO ya viene validado por el resolver, la lógica está en
 * los casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/warehouses')]
#[IsGranted('ROLE_API_ADMIN')]
final class WarehouseController
{
    public function __construct(
        private readonly ListWarehouses $listWarehouses,
        private readonly GetWarehouse $getWarehouse,
        private readonly CreateWarehouse $createWarehouse,
        private readonly UpdateWarehouse $updateWarehouse,
        private readonly ActivateWarehouse $activateWarehouse,
        private readonly DeactivateWarehouse $deactivateWarehouse,
        private readonly DiscardWarehouse $discardWarehouse,
        private readonly GetWarehouseDefaults $getDefaults,
    ) {
    }

    #[Route('', name: 'market_warehouses_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar los almacenes (filtros: q, regime, status)', tag: 'Market', responseDto: WarehouseOutDto::class, responseIsArray: true)]
    public function list(Request $request): JsonResponse
    {
        $q = $request->query->get('q');
        $regime = $request->query->get('regime');
        $status = $request->query->get('status');

        return new JsonResponse(array_map(
            static fn (WarehouseDetails $d) => WarehouseOutDto::from($d),
            ($this->listWarehouses)(
                is_string($q) ? $q : null,
                is_string($regime) && in_array($regime, ['platform', 'partner'], true) ? $regime : null,
                is_string($status) && in_array($status, ['draft', 'active', 'inactive'], true) ? $status : null,
            ),
        ));
    }

    #[Route('/defaults', name: 'market_warehouses_defaults', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Valores que propone el alta de un almacén (el municipio por defecto de Configuración)', tag: 'Market')]
    public function defaults(): JsonResponse
    {
        $m = ($this->getDefaults)();

        return new JsonResponse(['municipality' => $m === null ? null : ['id' => $m->id, 'name' => $m->name, 'provinceName' => $m->provinceName, 'selectable' => $m->selectable]]);
    }

    #[Route('/{id}', name: 'market_warehouses_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener un almacén', tag: 'Market', responseDto: WarehouseOutDto::class)]
    public function get(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->getWarehouse)($id));
    }

    #[Route('', name: 'market_warehouses_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear un almacén en borrador (solo nombre e identificador)', tag: 'Market', requestDto: CreateWarehouseDto::class, responseDto: WarehouseOutDto::class, responseStatusCode: 201)]
    public function create(CreateWarehouseDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->createWarehouse)((string) $dto->getName(), $dto->getSlug()), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'market_warehouses_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Cambiar los datos, el dueño, el municipio y los permisos del socio (el identificador no cambia)', tag: 'Market', requestDto: UpdateWarehouseDto::class, responseDto: WarehouseOutDto::class)]
    public function update(int $id, UpdateWarehouseDto $dto): JsonResponse
    {
        return $this->respond(fn () => ($this->updateWarehouse)($id, new WarehouseInput(
            (string) $dto->getName(),
            $dto->getAddress(),
            $dto->getMunicipalityId(),
            $dto->getOrgUnitId(),
            $dto->getCostCenterId(),
            new PartnerPermissions((bool) $dto->getSetsCoverage(), (bool) $dto->getAssemblesCombos(), (bool) $dto->getSeesSupplyCost()),
        )));
    }

    #[Route('/{id}/activate', name: 'market_warehouses_activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar (o reactivar) un almacén: exige dueño, centro de costo y municipio válidos', tag: 'Market', responseDto: WarehouseOutDto::class)]
    public function activate(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->activateWarehouse)($id));
    }

    #[Route('/{id}/deactivate', name: 'market_warehouses_deactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Desactivar un almacén activo: no acepta ventas nuevas, lo ya vendido sigue su curso', tag: 'Market', responseDto: WarehouseOutDto::class)]
    public function deactivate(int $id): JsonResponse
    {
        return $this->respond(fn () => ($this->deactivateWarehouse)($id));
    }

    #[Route('/{id}', name: 'market_warehouses_discard', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Descartar un borrador (el único caso en que se borra un almacén)', tag: 'Market', responseStatusCode: 204)]
    public function discard(int $id): JsonResponse
    {
        try {
            ($this->discardWarehouse)($id);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (WarehouseNotFound $e) {
            return $this->error('WAREHOUSE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (WarehouseTransitionInvalid $e) {
            return $this->error('WAREHOUSE_TRANSITION_INVALID', $e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @param callable(): WarehouseDetails $action
     */
    private function respond(callable $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            return new JsonResponse(WarehouseOutDto::from($action()), $status);
        } catch (WarehouseNotFound $e) {
            return $this->error('WAREHOUSE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (WarehouseAlreadyExists $e) {
            return $this->error('WAREHOUSE_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (MainWarehouseProtected $e) {
            return $this->error('MAIN_WAREHOUSE_PROTECTED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (WarehouseTransitionInvalid $e) {
            return $this->error('WAREHOUSE_TRANSITION_INVALID', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (WarehouseIncomplete $e) {
            return $this->error('WAREHOUSE_INCOMPLETE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, ['missing' => $e->missing()]);
        } catch (WarehouseOwnerInvalid $e) {
            return $this->error('WAREHOUSE_OWNER_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (WarehouseMunicipalityInvalid $e) {
            return $this->error('WAREHOUSE_MUNICIPALITY_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidWarehouse|InvalidSlug $e) {
            return $this->error('INVALID_WAREHOUSE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code] + $extra], $status);
    }
}
