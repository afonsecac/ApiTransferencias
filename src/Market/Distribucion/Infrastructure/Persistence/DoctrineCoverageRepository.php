<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Persistence;

use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCoverageRepository implements CoverageRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $warehouseId, int $municipalityId): ?DeliveryCoverage
    {
        return $this->em->getRepository(DeliveryCoverage::class)->findOneBy(['warehouseId' => $warehouseId, 'municipalityId' => $municipalityId]);
    }

    public function ofWarehouse(int $warehouseId): array
    {
        /** @var list<DeliveryCoverage> $rows */
        $rows = $this->em->getRepository(DeliveryCoverage::class)->findBy(['warehouseId' => $warehouseId], ['id' => 'ASC']);

        return $rows;
    }

    public function activeInMunicipality(int $municipalityId): array
    {
        /** @var list<DeliveryCoverage> $rows */
        $rows = $this->em->getRepository(DeliveryCoverage::class)->findBy(['municipalityId' => $municipalityId, 'active' => true], ['id' => 'ASC']);

        return $rows;
    }

    public function save(DeliveryCoverage $coverage): void
    {
        $this->em->persist($coverage);
        $this->em->flush();
    }
}
