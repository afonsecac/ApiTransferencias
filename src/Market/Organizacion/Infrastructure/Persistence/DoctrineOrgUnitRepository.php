<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Persistence;

use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrgUnitRepository implements OrgUnitRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?OrgUnit
    {
        return $this->em->find(OrgUnit::class, $id);
    }

    public function findByTaxId(string $countryCode, string $taxId): ?OrgUnit
    {
        return $this->em->getRepository(OrgUnit::class)->findOneBy(['countryCode' => $countryCode, 'taxId' => $taxId]);
    }

    public function search(?OrgUnitType $type, ?string $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(OrgUnit::class, 'u')
            ->orderBy('u.name', 'ASC')
            ->addOrderBy('u.id', 'ASC');

        if ($query !== null && $query !== '') {
            $qb->andWhere(
                'LOWER(u.name) LIKE :q OR LOWER(u.legalName) LIKE :q OR LOWER(u.taxId) LIKE :q'
                . ' OR EXISTS (SELECT 1 FROM ' . CostCenter::class . ' c WHERE c.orgUnit = u AND (LOWER(c.code) LIKE :q OR LOWER(c.name) LIKE :q))'
            )->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        /** @var list<OrgUnit> $units */
        $units = $qb->getQuery()->getResult();
        if ($type === null) {
            return $units;
        }

        // El JSON de tipos se filtra en PHP: son pocas unidades y así no depende de operadores de Postgres en DQL.
        return array_values(array_filter($units, static fn (OrgUnit $u) => in_array($type, $u->types(), true)));
    }

    public function save(OrgUnit $unit, array $costCenters = []): void
    {
        $this->em->persist($unit);
        foreach ($costCenters as $center) {
            $this->em->persist($center);
        }
        // Un solo flush = una sola transacción: la unidad y sus centros se guardan juntos o no se guarda nada.
        $this->em->flush();
    }

    public function costCentersOf(OrgUnit $unit): array
    {
        /** @var list<CostCenter> $centers */
        $centers = $this->em->getRepository(CostCenter::class)->findBy(['orgUnit' => $unit], ['code' => 'ASC']);

        return $centers;
    }

    public function costCentersOfMany(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        /** @var list<CostCenter> $centers */
        $centers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(CostCenter::class, 'c')
            ->where('IDENTITY(c.orgUnit) IN (:ids)')
            ->setParameter('ids', $unitIds)
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();

        $byUnit = [];
        foreach ($centers as $center) {
            $byUnit[(int) $center->orgUnit()->id()][] = $center;
        }

        return $byUnit;
    }
}
