<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
    }

    public function search(?string $query, ?ProductStatus $status, ?int $sellerId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.sku', 'ASC');

        if ($query !== null && $query !== '') {
            // DQL no sabe de JSON: los ids que coinciden por sku o por el nombre en cualquier idioma se buscan en SQL.
            $qb->andWhere('p.id IN (:matching)')->setParameter('matching', $this->idsMatching($query) ?: [0]);
        }
        if ($status !== null) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status->value);
        }
        if ($sellerId !== null) {
            $qb->andWhere('p.sellerId = :seller')->setParameter('seller', $sellerId);
        }

        /** @var list<Product> $products */
        $products = $qb->getQuery()->getResult();

        return $products;
    }

    /**
     * @return list<int>
     */
    private function idsMatching(string $query): array
    {
        $ids = $this->em->getConnection()->fetchFirstColumn(
            "SELECT p.id FROM market_product p
             WHERE LOWER(p.sku) LIKE :q
                OR EXISTS (SELECT 1 FROM json_each(p.texts) AS t WHERE LOWER(t.value->>'name') LIKE :q)",
            ['q' => '%' . mb_strtolower(addcslashes($query, '%_\\')) . '%'],
        );

        return array_map('intval', $ids);
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
        $this->em->flush();
    }
}
