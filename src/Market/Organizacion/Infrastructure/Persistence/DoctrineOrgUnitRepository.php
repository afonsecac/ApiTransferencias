<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Persistence;

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
            $qb->andWhere('LOWER(u.name) LIKE :q OR LOWER(u.legalName) LIKE :q OR LOWER(u.taxId) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }
        if ($type !== null) {
            // El JSON de tipos se filtra en PHP: son pocas unidades y así no depende de operadores de Postgres en DQL.
            $units = array_filter(
                $qb->getQuery()->getResult(),
                static fn (OrgUnit $u) => in_array($type, $u->types(), true),
            );

            return array_values($units);
        }

        /** @var list<OrgUnit> $units */
        $units = $qb->getQuery()->getResult();

        return $units;
    }

    public function save(OrgUnit $unit): void
    {
        $this->em->persist($unit);
        $this->em->flush();
    }
}
