<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Entity\User;
use App\Market\Inventario\Application\ChangeWarehouseUserPermissions;
use App\Market\Inventario\Application\InviteWarehouseUser;
use App\Market\Inventario\Application\ListWarehouseUsers;
use App\Market\Inventario\Application\ResendWarehouseUserInvitation;
use App\Market\Inventario\Application\SetWarehouseUserActive;
use App\Market\Inventario\Application\WarehouseUserRow;
use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\StaffAlreadyActivated;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseUserAlreadyLinked;
use App\Market\Inventario\Domain\WarehouseUserNotFound;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Usuarios de un almacén (COMR-287): el personal de la plataforma vinculado a un almacén con sus permisos. El controlador solo
 * traduce HTTP. Los usuarios externos de un socio y su aislamiento son de COMR-288.
 */
#[AsController]
#[Route('/market/warehouses/{warehouseId}/users', requirements: ['warehouseId' => '\d+'])]
#[IsGranted('ROLE_API_ADMIN')]
final class WarehouseUserController
{
    public function __construct(
        private readonly ListWarehouseUsers $listUsers,
        private readonly InviteWarehouseUser $inviteUser,
        private readonly ChangeWarehouseUserPermissions $changePermissions,
        private readonly SetWarehouseUserActive $setActive,
        private readonly ResendWarehouseUserInvitation $resend,
        private readonly Security $security,
        #[Autowire(service: 'limiter.resend_activation')]
        private readonly RateLimiterFactory $resendLimiter,
    ) {
    }

    #[Route('', name: 'market_warehouse_users_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar los usuarios de un almacén con su estado (invitado, activo o inactivo), permisos y 2FA', tag: 'Market', responseDto: WarehouseUserOutDto::class, responseIsArray: true)]
    public function list(int $warehouseId): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(array_map(static fn (WarehouseUserRow $r) => WarehouseUserOutDto::from($r), ($this->listUsers)($warehouseId))));
    }

    #[Route('', name: 'market_warehouse_users_invite', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Invitar a un usuario a un almacén: vincula al existente o crea uno nuevo con invitación de activación', tag: 'Market', requestDto: InviteWarehouseUserDto::class, responseDto: WarehouseUserOutDto::class, responseStatusCode: 201)]
    public function invite(int $warehouseId, InviteWarehouseUserDto $dto): JsonResponse
    {
        $user = $this->security->getUser();

        return $this->guard(fn () => new JsonResponse(
            WarehouseUserOutDto::from(($this->inviteUser)(
                $warehouseId,
                (string) $dto->getEmail(),
                $dto->getFirstName(),
                $dto->getLastName(),
                new WarehouseUserPermissions((bool) $dto->getStock(), (bool) $dto->getAssembly(), (bool) $dto->getTransfers(), (bool) $dto->getDeliveries()),
                $user instanceof User ? $user->getId() : null,
            )),
            Response::HTTP_CREATED,
        ));
    }

    #[Route('/{userId}/permissions', name: 'market_warehouse_users_permissions', methods: ['PUT'], requirements: ['userId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Cambiar los permisos de un usuario en el almacén (al menos uno)', tag: 'Market', requestDto: WarehouseUserPermissionsDto::class, responseDto: WarehouseUserOutDto::class)]
    public function permissions(int $warehouseId, int $userId, WarehouseUserPermissionsDto $dto): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(WarehouseUserOutDto::from(($this->changePermissions)(
            $warehouseId,
            $userId,
            new WarehouseUserPermissions((bool) $dto->getStock(), (bool) $dto->getAssembly(), (bool) $dto->getTransfers(), (bool) $dto->getDeliveries()),
        ))));
    }

    #[Route('/{userId}/active', name: 'market_warehouse_users_active', methods: ['POST'], requirements: ['userId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar a un usuario en el almacén (no se borra)', tag: 'Market', requestDto: SetWarehouseUserActiveDto::class, responseDto: WarehouseUserOutDto::class)]
    public function setActive(int $warehouseId, int $userId, SetWarehouseUserActiveDto $dto): JsonResponse
    {
        return $this->guard(fn () => new JsonResponse(WarehouseUserOutDto::from(($this->setActive)($warehouseId, $userId, (bool) $dto->getActive()))));
    }

    #[Route('/{userId}/resend', name: 'market_warehouse_users_resend', methods: ['POST'], requirements: ['userId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Reenviar la invitación de activación (409 si ya activó la cuenta; mismo límite de reenvíos que el resto)', tag: 'Market')]
    public function resend(int $warehouseId, int $userId): JsonResponse
    {
        if (!$this->resendLimiter->create('user_' . $userId)->consume()->isAccepted()) {
            return $this->error('TOO_MANY_REQUESTS', 'Too many requests. Please try again later.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $this->guard(function () use ($warehouseId, $userId): JsonResponse {
            ($this->resend)($warehouseId, $userId);

            return new JsonResponse(['resent' => true]);
        });
    }

    /**
     * @param callable(): JsonResponse $action
     */
    private function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (WarehouseNotFound|WarehouseUserNotFound $e) {
            return $this->error('WAREHOUSE_USER_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (WarehouseUserAlreadyLinked $e) {
            return $this->error('WAREHOUSE_USER_ALREADY_LINKED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (StaffAlreadyActivated $e) {
            return $this->error('ACCOUNT_ALREADY_ACTIVATED', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (InvalidWarehouseUser $e) {
            return $this->error('INVALID_WAREHOUSE_USER', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
