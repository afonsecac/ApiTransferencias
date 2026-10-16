<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWarehouseRepository implements WarehouseRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Warehouse
    {
        return $this->em->find(Warehouse::class, $id);
    }

    public function findBySlug(string $slug): ?Warehouse
    {
        return $this->em->getRepository(Warehouse::class)->findOneBy(['slug' => $slug]);
    }

    public function findAll(): array
    {
        /** @var list<Warehouse> $all */
        $all = $this->em->getRepository(Warehouse::class)->findBy([], ['id' => 'ASC']);

        return $all;
    }

    public function countByOrgUnit(int $orgUnitId): int
    {
        return $this->em->getRepository(Warehouse::class)->count(['orgUnitId' => $orgUnitId]);
    }

    public function save(Warehouse $warehouse): void
    {
        $this->em->persist($warehouse);
        $this->em->flush();
    }

    public function remove(Warehouse $warehouse): void
    {
        $this->em->remove($warehouse);
        $this->em->flush();
    }
}
