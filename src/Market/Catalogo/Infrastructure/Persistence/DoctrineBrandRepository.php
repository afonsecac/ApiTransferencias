<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\BrandRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBrandRepository implements BrandRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Brand
    {
        return $this->em->find(Brand::class, $id);
    }

    public function findBySlug(string $slug): ?Brand
    {
        return $this->em->getRepository(Brand::class)->findOneBy(['slug' => $slug]);
    }

    public function search(?string $query, ?int $sellerId, ?bool $active): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Brand::class, 'b')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('b.id', 'ASC');

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(b.name) LIKE :q OR LOWER(b.slug) LIKE :q')->setParameter('q', '%' . mb_strtolower($query) . '%');
        }
        if ($sellerId !== null) {
            $qb->andWhere('b.sellerId = :seller')->setParameter('seller', $sellerId);
        }
        if ($active !== null) {
            $qb->andWhere('b.active = :active')->setParameter('active', $active);
        }

        /** @var list<Brand> $brands */
        $brands = $qb->getQuery()->getResult();

        return $brands;
    }

    public function save(Brand $brand): void
    {
        $this->em->persist($brand);
        $this->em->flush();
    }
}
