<?php

declare(strict_types=1);

namespace App\Market\Geografia\Infrastructure\Persistence;

use App\Market\Geografia\Domain\GeographyRepository;
use App\Market\Geografia\Domain\Municipality;
use App\Market\Geografia\Domain\Province;
use App\Market\Geografia\Domain\SearchText;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineGeographyRepository implements GeographyRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findProvince(int $id): ?Province
    {
        return $this->em->find(Province::class, $id);
    }

    public function findMunicipality(int $id): ?Municipality
    {
        return $this->em->find(Municipality::class, $id);
    }

    public function findMunicipalityByCode(string $code): ?Municipality
    {
        return $this->em->getRepository(Municipality::class)->findOneBy(['code' => $code]);
    }

    public function provinces(): array
    {
        /** @var list<Province> $provinces */
        $provinces = $this->em->getRepository(Province::class)->findBy([], ['id' => 'ASC']);

        return $provinces;
    }

    public function municipalities(?string $query, ?int $provinceId, ?bool $selectable): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('m')
            ->from(Municipality::class, 'm')
            ->join(Province::class, 'p', 'ON', 'p.id = m.provinceId')
            ->orderBy('m.searchName', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        $needle = $query === null ? '' : SearchText::normalize($query);
        if ($needle !== '') {
            $qb->andWhere('m.searchName LIKE :q')->setParameter('q', '%' . addcslashes($needle, '%_\\') . '%');
        }
        if ($provinceId !== null) {
            $qb->andWhere('m.provinceId = :province')->setParameter('province', $provinceId);
        }
        if ($selectable === true) {
            $qb->andWhere('m.active = TRUE AND p.active = TRUE');
        } elseif ($selectable === false) {
            $qb->andWhere('m.active = FALSE OR p.active = FALSE');
        }

        /** @var list<Municipality> $found */
        $found = $qb->getQuery()->getResult();

        return $found;
    }

    public function municipalityCounts(): array
    {
        $rows = $this->em->createQuery('SELECT m.provinceId AS province, COUNT(m.id) AS total FROM ' . Municipality::class . ' m GROUP BY m.provinceId')->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['province']] = (int) $row['total'];
        }

        return $counts;
    }

    public function save(Province|Municipality $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }
}
