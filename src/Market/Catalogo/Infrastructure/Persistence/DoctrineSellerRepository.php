<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSellerRepository implements SellerRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Seller
    {
        return $this->em->find(Seller::class, $id);
    }

    public function findBySlug(string $slug): ?Seller
    {
        return $this->em->getRepository(Seller::class)->findOneBy(['slug' => $slug]);
    }

    public function findByOrgUnit(int $orgUnitId): ?Seller
    {
        return $this->em->getRepository(Seller::class)->findOneBy(['orgUnitId' => $orgUnitId]);
    }

    public function findPlatform(): ?Seller
    {
        return $this->em->getRepository(Seller::class)->findOneBy(['platform' => true]);
    }

    public function search(?string $query, ?bool $active): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Seller::class, 's')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('s.id', 'ASC');

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(s.name) LIKE :q OR LOWER(s.slug) LIKE :q')->setParameter('q', '%' . mb_strtolower($query) . '%');
        }
        if ($active !== null) {
            $qb->andWhere('s.active = :active')->setParameter('active', $active);
        }

        /** @var list<Seller> $sellers */
        $sellers = $qb->getQuery()->getResult();

        return $sellers;
    }

    public function save(Seller $seller): void
    {
        $this->em->persist($seller);
        $this->em->flush();
    }
}
