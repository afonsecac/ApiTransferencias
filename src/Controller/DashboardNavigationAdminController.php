<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\NavigationItem;
use App\Entity\User;
use App\Entity\UserPermission;
use App\Enums\NavigationTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/navigation-admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class DashboardNavigationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    // ─── Navigation Items ───────────────────────────────────

    #[Route('/items', name: 'dashboard_nav_items_list', methods: ['GET'])]
    public function listItems(): JsonResponse
    {
        $items = $this->em->getRepository(NavigationItem::class)->createQueryBuilder('ni')
            ->leftJoin('ni.userPermissions', 'up')
            ->addSelect('up')
            ->orderBy('ni.orderValue', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn(NavigationItem $item) => $this->normalizeItem($item), $items);

        return $this->json($data);
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_show', methods: ['GET'])]
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
    public function createItem(Request $request): JsonResponse
    {
        $data = $request->request->all();

        if (empty($data['title'])) {
            return $this->json(['error' => ['message' => 'title is required']], Response::HTTP_BAD_REQUEST);
        }
        if (empty($data['type'])) {
            return $this->json(['error' => ['message' => 'type is required']], Response::HTTP_BAD_REQUEST);
        }

        $item = new NavigationItem();
        $this->hydrateItem($item, $data);
        $item->setCreatedAt(new \DateTimeImmutable('now'));
        $item->setUpdatedAt(new \DateTimeImmutable('now'));

        $this->em->persist($item);

        // Crear permiso por defecto si se envía minRoleRequired
        if (!empty($data['minRoleRequired'])) {
            $permission = new UserPermission();
            $permission->setItem($item);
            $permission->setMinRoleRequired($data['minRoleRequired']);
            $permission->setActive(true);
            if (!empty($data['clientId'])) {
                $client = $this->em->getRepository(Client::class)->find($data['clientId']);
                $permission->setClient($client);
            }
            if (!empty($data['userId'])) {
                $user = $this->em->getRepository(User::class)->find($data['userId']);
                $permission->setUserInfo($user);
            }
            $this->em->persist($permission);
        }

        $this->em->flush();

        return $this->json($this->normalizeItem($item), Response::HTTP_CREATED);
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_update', methods: ['PUT'])]
    public function updateItem(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $this->hydrateItem($item, $data);
        $item->setUpdatedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        return $this->json($this->normalizeItem($item));
    }

    #[Route('/items/{id}', name: 'dashboard_nav_items_delete', methods: ['DELETE'])]
    public function deleteItem(int $id): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($id);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        // No permitir eliminar items con hijos
        if ($item->getChildren()->count() > 0) {
            return $this->json([
                'error' => ['message' => 'Cannot delete item with children. Remove children first.'],
            ], Response::HTTP_CONFLICT);
        }

        // Eliminar permisos asociados
        foreach ($item->getUserPermissions() as $permission) {
            $this->em->remove($permission);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    #[Route('/items/{id}/toggle', name: 'dashboard_nav_items_toggle', methods: ['PATCH'])]
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
    public function createPermission(int $itemId, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(NavigationItem::class)->find($itemId);
        if ($item === null) {
            return $this->json(['error' => ['message' => 'Item not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        if (empty($data['minRoleRequired'])) {
            return $this->json(['error' => ['message' => 'minRoleRequired is required']], Response::HTTP_BAD_REQUEST);
        }

        $permission = new UserPermission();
        $permission->setItem($item);
        $permission->setMinRoleRequired($data['minRoleRequired']);
        $permission->setActive($data['isActive'] ?? true);

        if (!empty($data['clientId'])) {
            $permission->setClient($this->em->getRepository(Client::class)->find($data['clientId']));
        }
        if (!empty($data['userId'])) {
            $permission->setUserInfo($this->em->getRepository(User::class)->find($data['userId']));
        }

        $this->em->persist($permission);
        $this->em->flush();

        return $this->json(
            $this->serializer->normalize($permission, 'json', [
                'groups' => ['permission:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ]),
            Response::HTTP_CREATED
        );
    }

    #[Route('/permissions/{id}', name: 'dashboard_nav_permissions_update', methods: ['PUT'])]
    public function updatePermission(int $id, Request $request): JsonResponse
    {
        $permission = $this->em->getRepository(UserPermission::class)->find($id);
        if ($permission === null) {
            return $this->json(['error' => ['message' => 'Permission not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        if (isset($data['minRoleRequired'])) {
            $permission->setMinRoleRequired($data['minRoleRequired']);
        }
        if (isset($data['isActive'])) {
            $permission->setActive((bool) $data['isActive']);
        }
        if (array_key_exists('clientId', $data)) {
            $permission->setClient(
                $data['clientId'] ? $this->em->getRepository(Client::class)->find($data['clientId']) : null
            );
        }
        if (array_key_exists('userId', $data)) {
            $permission->setUserInfo(
                $data['userId'] ? $this->em->getRepository(User::class)->find($data['userId']) : null
            );
        }

        $this->em->flush();

        return $this->json(
            $this->serializer->normalize($permission, 'json', [
                'groups' => ['permission:read'],
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ])
        );
    }

    #[Route('/permissions/{id}', name: 'dashboard_nav_permissions_delete', methods: ['DELETE'])]
    public function deletePermission(int $id): JsonResponse
    {
        $permission = $this->em->getRepository(UserPermission::class)->find($id);
        if ($permission === null) {
            return $this->json(['error' => ['message' => 'Permission not found']], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($permission);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    // ─── Helpers ────────────────────────────────────────────

    private function hydrateItem(NavigationItem $item, array $data): void
    {
        if (isset($data['title'])) {
            $item->setTitle(mb_substr($data['title'], 0, 255));
        }
        if (isset($data['subtitle'])) {
            $item->setSubtitle(mb_substr($data['subtitle'], 0, 255));
        }
        if (isset($data['type'])) {
            $item->setType(NavigationTypeEnum::tryFrom($data['type']));
        }
        if (isset($data['icon'])) {
            $item->setIcon(mb_substr($data['icon'], 0, 80));
        }
        if (isset($data['link'])) {
            $item->setLink(mb_substr($data['link'], 0, 255));
        }
        if (array_key_exists('parentId', $data)) {
            $parent = $data['parentId']
                ? $this->em->getRepository(NavigationItem::class)->find($data['parentId'])
                : null;
            $item->setParent($parent);
        }
        if (isset($data['active'])) {
            $item->setActive((bool) $data['active']);
        } elseif ($item->active() === null) {
            $item->setActive(true);
        }
        if (isset($data['orderValue'])) {
            $item->setOrderValue(mb_substr($data['orderValue'], 0, 5));
        }
        if (array_key_exists('badge', $data)) {
            $item->setBadge($data['badge']);
        }
        if (array_key_exists('data', $data)) {
            $item->setData($data['data']);
        }
    }

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
