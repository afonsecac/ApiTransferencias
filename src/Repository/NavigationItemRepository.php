<?php

namespace App\Repository;

use App\Entity\NavigationItem;
use App\Entity\UserPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NavigationItem>
 */
class NavigationItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NavigationItem::class);
    }

    /**
     * @return NavigationItem[]
     */
    public function getNavigationItems(): array
    {
        return $this->createQueryBuilder('ni')
            ->select('ni')
            ->leftJoin('ni.children', 'c')
            ->where('ni.parent IS NULL')
            ->andWhere('ni.active = :is_active')
            ->setParameter('is_active', true)
            ->orderBy('ni.orderValue', 'ASC')
            ->addOrderBy('ni.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devuelve los items de navegación filtrados por permisos del usuario.
     *
     * Un item es visible si el usuario tiene acceso por:
     * - Rol: su minRoleRequired está en los roles del usuario
     * - Client: el permiso está asignado al client del usuario
     * - Usuario: el permiso está asignado directamente al usuario
     */
    public function getNavigationByUserAndClient(array $roles, int $clientId = null, int $userId = null): array
    {
        $allowedIds = $this->getAllowedItemIds($roles, $clientId, $userId);

        if (empty($allowedIds)) {
            return [];
        }

        return $this->createQueryBuilder('ni')
            ->select('ni')
            ->leftJoin('ni.children', 'c')
            ->addSelect('c')
            ->where('ni.parent IS NULL')
            ->andWhere('ni.active = :is_active')
            ->andWhere('ni.id IN (:allowedIds)')
            ->andWhere('c.active = :is_active OR c.id IS NULL')
            ->setParameter('is_active', true)
            ->setParameter('allowedIds', $allowedIds)
            ->orderBy('ni.orderValue', 'ASC')
            ->addOrderBy('c.orderValue', 'ASC')
            ->addOrderBy('ni.title', 'ASC')
            ->addOrderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devuelve los IDs de parent y child a los que el usuario tiene acceso.
     */
    public function accessIds(array $roles, int $clientId = null, int $userId = null): array
    {
        $allowedIds = $this->getAllowedItemIds($roles, $clientId, $userId);

        $result = [];
        foreach ($allowedIds as $id) {
            $item = $this->find($id);
            if ($item === null) {
                continue;
            }
            $parent = $item->getParent();
            if ($parent !== null) {
                $result[] = ['parentId' => $parent->getId(), 'childId' => $id];
            } else {
                $result[] = ['parentId' => $id, 'childId' => $id];
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los IDs de NavigationItem a los que el usuario tiene acceso,
     * verificando permisos tanto en padres como en hijos.
     *
     * @return int[]
     */
    public function getAllowedItemIds(array $roles, ?int $clientId, ?int $userId): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(up.item) as itemId')
            ->from(UserPermission::class, 'up')
            ->where('up.isActive = :is_active')
            ->setParameter('is_active', true);

        $conditions = [];

        // Condición por rol: el minRoleRequired debe estar en los roles del usuario
        if (!empty($roles)) {
            $conditions[] = 'up.minRoleRequired IN (:roles)';
            $qb->setParameter('roles', $roles);
        }

        // Condición por client
        if ($clientId !== null) {
            $conditions[] = 'up.client = :clientId';
            $qb->setParameter('clientId', $clientId);
        }

        // Condición por usuario específico
        if ($userId !== null) {
            $conditions[] = 'up.userInfo = :userId';
            $qb->setParameter('userId', $userId);
        }

        if (empty($conditions)) {
            return [];
        }

        $qb->andWhere(implode(' OR ', $conditions));

        $rows = $qb->getQuery()->getScalarResult();
        $allowedItemIds = array_unique(array_column($rows, 'itemId'));

        // Para cada hijo permitido, incluir también su padre
        // Para cada padre permitido, no incluir hijos automáticamente
        $allIds = [];
        foreach ($allowedItemIds as $itemId) {
            $allIds[] = (int) $itemId;
            $item = $this->find($itemId);
            if ($item !== null && $item->getParent() !== null) {
                $allIds[] = $item->getParent()->getId();
            }
        }

        return array_unique($allIds);
    }
}
