<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWarehouseUserRepository implements WarehouseUserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $userId, int $warehouseId): ?WarehouseUser
    {
        return $this->em->getRepository(WarehouseUser::class)->findOneBy(['userId' => $userId, 'warehouseId' => $warehouseId]);
    }

    public function ofWarehouse(int $warehouseId): array
    {
        /** @var list<WarehouseUser> $links */
        $links = $this->em->getRepository(WarehouseUser::class)->findBy(['warehouseId' => $warehouseId], ['id' => 'ASC']);

        return $links;
    }

    public function ofUser(int $userId): array
    {
        /** @var list<WarehouseUser> $links */
        $links = $this->em->getRepository(WarehouseUser::class)->findBy(['userId' => $userId], ['id' => 'ASC']);

        return $links;
    }

    public function save(WarehouseUser $link): void
    {
        $this->em->persist($link);
        $this->em->flush();
    }
}
