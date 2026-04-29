<?php

namespace App\Controller;

use App\DTO\CreateNavigationItemDto;
use App\DTO\CreateUserPermissionDto;
use App\DTO\UpdateNavigationItemDto;
use App\DTO\UpdateUserPermissionDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\ToggleNavItemOutDto;
use App\Entity\NavigationItem;
use App\Entity\UserPermission;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\NavigationItemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/navigation-admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class DashboardNavigationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly NavigationItemService $navigationItemService,
    ) {
    }

    // ─── Navigation Items ───────────────────────────────────

    #[Route('/items', name: 'dashboard_nav_items_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar items de navegación', tag: 'Navigation Admin')]
    public function listItems(): JsonResponse
    {
        $items = $this->em->getRepository(NavigationItem::class)->createQueryBuilder('ni')
            ->leftJoin('ni.userPermissions', 'up')
            ->addSelect('up')
            ->orderBy('ni.orderValue', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(NavigationItem $item) => $this->normalizeItem($item), $items));
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_show', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Obtener item de navegación', tag: 'Navigation Admin')]
    public function showItem(int $id): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $this->normalizeItem($item);
        $data['permissions'] = $this->serializer->normalize(
            $item->getUserPermissions()->toArray(),
            'json',
            ['groups' => ['permission:read'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
        );

        return $this->json($data);
    }

    #[Route('/items', name: 'dashboard_nav_items_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear item de navegación', tag: 'Navigation Admin', requestDto: CreateNavigationItemDto::class, responseStatusCode: 201)]
    public function createItem(CreateNavigationItemDto $dto): JsonResponse
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        try {
            $item = $this->navigationItemService->createItem($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->normalizeItem($item), Response::HTTP_CREATED);
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_update', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Actualizar item de navegación', tag: 'Navigation Admin', requestDto: UpdateNavigationItemDto::class)]
    public function updateItem(int $id, UpdateNavigationItemDto $dto): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->navigationItemService->updateItem($item, $dto);

        return $this->json($this->normalizeItem($item));
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_delete', methods: ['DELETE'])]
    #[DashboardEndpoint(summary: 'Eliminar item de navegación', tag: 'Navigation Admin', responseDto: DeletedOutDto::class)]
    public function deleteItem(int $id): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        if ($item->getChildren()->count() > 0) {
            return $this->json([
                'error' => ['message' => 'Cannot delete item with children. Remove children first.'],
            ], Response::HTTP_CONFLICT);
        }

        $this->navigationItemService->deleteItem($item);

        return $this->json(['deleted' => true]);
    }

    #[Route('/items/{id}/toggle', name: 'dashboard_nav_items_toggle', methods: ['PATCH'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar item de navegación', tag: 'Navigation Admin', responseDto: ToggleNavItemOutDto::class)]
    public function toggleItem(int $id): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $item->setActive(!$item->active());
        $item->setUpdatedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $this->json(['id' => $item->getId(), 'active' => $item->active()]);
    }

    // ─── Permissions ────────────────────────────────────────

    #[Route('/items/{itemId}/permissions', name: 'dashboard_nav_permissions_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar permisos de item', tag: 'Navigation Admin')]
    public function listPermissions(int $itemId): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($itemId);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize(
                $item->getUserPermissions()->toArray(),
                'json',
                ['groups' => ['permission:read'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
            )
        );
    }

    #[Route('/items/{itemId}/permissions', name: 'dashboard_nav_permissions_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear permiso de item', tag: 'Navigation Admin', requestDto: CreateUserPermissionDto::class, responseStatusCode: 201)]
    public function createPermission(int $itemId, CreateUserPermissionDto $dto): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($itemId);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        try {
            $permission = $this->navigationItemService->createPermission($item, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(
            $this->serializer->normalize($permission, 'json', [
                'groups' => ['permission:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]),
            Response::HTTP_CREATED
        );
    }

    #[Route('/permissions/{id}', name: 'dashboard_nav_permissions_update', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Actualizar permiso', tag: 'Navigation Admin', requestDto: UpdateUserPermissionDto::class)]
    public function updatePermission(int $id, UpdateUserPermissionDto $dto): JsonResponse
    {
        $permission = $this->em->getRepository(UserPermission::class)->find($id);
        if ($permission === null) {
            return $this->json(['error' => ['message' => 'Permission not found']], Response::HTTP_NOT_FOUND);
        }

        $permission = $this->navigationItemService->updatePermission($permission, $dto);

        return $this->json(
            $this->serializer->normalize($permission, 'json', [
                'groups' => ['permission:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ])
        );
    }

    #[Route('/permissions/{id}', name: 'dashboard_nav_permissions_delete', methods: ['DELETE'])]
    #[DashboardEndpoint(summary: 'Eliminar permiso', tag: 'Navigation Admin', responseDto: DeletedOutDto::class)]
    public function deletePermission(int $id): JsonResponse
    {
        $permission = $this->em->getRepository(UserPermission::class)->find($id);
        if ($permission === null) {
            return $this->json(['error' => ['message' => 'Permission not found']], Response::HTTP_NOT_FOUND);
        }

        $this->navigationItemService->deletePermission($permission);

        return $this->json(['deleted' => true]);
    }

    // ─── Helpers ────────────────────────────────────────────

    private function normalizeItem(NavigationItem $item): array
    {
        $data = $this->serializer->normalize($item, 'json', [
            'groups' => ['navItem:read'],
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
        $data['parentId'] = $item->getParent()?->getId();
        return $data;
    }
}
