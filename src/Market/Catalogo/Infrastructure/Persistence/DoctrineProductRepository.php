<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductFilter;
use App\Market\Catalogo\Domain\ProductRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Product
    {
        $product = $this->em->find(Product::class, $id);
        if ($product !== null) {
            $this->loadCategories([$product]);
        }

        return $product;
    }

    public function findBySku(string $sku): ?Product
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        if ($product !== null) {
            $this->loadCategories([$product]);
        }

        return $product;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        /** @var list<Product> $products */
        $products = $this->em->getRepository(Product::class)->findBy(['id' => $ids], ['id' => 'ASC']);
        $this->loadCategories($products);

        return $products;
    }

    public function productIdsByCategory(): array
    {
        $byCategory = [];
        foreach ($this->em->getConnection()->fetchAllNumeric('SELECT category_id, product_id FROM market_product_category') as [$categoryId, $productId]) {
            $byCategory[(int) $categoryId][] = (int) $productId;
        }

        return $byCategory;
    }

    public function hasPhysicalProducts(): bool
    {
        return $this->em->getConnection()->fetchOne('SELECT 1 FROM market_product WHERE content_base IS NOT NULL LIMIT 1') !== false;
    }

    public function countsByBrand(): array
    {
        return $this->countsBy('brand_id');
    }

    public function countsBySeller(): array
    {
        return $this->countsBy('seller_id');
    }

    /**
     * @param 'brand_id'|'seller_id' $column
     *
     * @return array<int, int>
     */
    private function countsBy(string $column): array
    {
        $counts = [];
        foreach ($this->em->getConnection()->fetchAllNumeric(sprintf('SELECT %1$s, COUNT(*) FROM market_product WHERE %1$s IS NOT NULL GROUP BY %1$s', $column)) as [$id, $count]) {
            $counts[(int) $id] = (int) $count;
        }

        return $counts;
    }

    public function skusStartingWith(string $prefix): array
    {
        /** @var list<string> $skus */
        $skus = $this->em->createQueryBuilder()
            ->select('p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku LIKE :prefix')
            ->setParameter('prefix', addcslashes(strtoupper($prefix), '%_\\') . '%')
            ->getQuery()
            ->getSingleColumnResult();

        return $skus;
    }

    public function search(ProductFilter $filter, ?int $limit = null, int $offset = 0): array
    {
        $qb = $this->filtered($filter)->select('p')->orderBy('p.sku', 'ASC');
        if ($limit !== null) {
            $qb->setMaxResults($limit)->setFirstResult($offset);
        }

        /** @var list<Product> $products */
        $products = $qb->getQuery()->getResult();
        $this->loadCategories($products);

        return $products;
    }

    public function count(ProductFilter $filter): int
    {
        return (int) $this->filtered($filter)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    private function filtered(ProductFilter $filter): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()->from(Product::class, 'p');

        if ($filter->query !== null && $filter->query !== '') {
            // DQL no sabe de JSON: los ids que coinciden por sku, nombre en cualquier idioma o código de barras se buscan en SQL.
            $qb->andWhere('p.id IN (:matching)')->setParameter('matching', $this->idsMatching($filter->query) ?: [0]);
        }
        if ($filter->status !== null) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filter->status->value);
        }
        if ($filter->sellerId !== null) {
            $qb->andWhere('p.sellerId = :seller')->setParameter('seller', $filter->sellerId);
        }
        if ($filter->brandId !== null) {
            $qb->andWhere('p.brandId = :brand')->setParameter('brand', $filter->brandId);
        }
        if ($filter->relevantOn !== null) {
            $qb->andWhere('p.relevanceLevel > 0')
                ->andWhere('p.relevanceFrom IS NULL OR p.relevanceFrom <= :day')
                ->andWhere('p.relevanceUntil IS NULL OR p.relevanceUntil >= :day')
                ->setParameter('day', $filter->relevantOn->setTime(0, 0), 'date_immutable');
        }
        if ($filter->categoryIds !== []) {
            // La tabla de unión no es una entidad: los ids de los productos de esas categorías se buscan en SQL.
            $qb->andWhere('p.id IN (:inCategories)')->setParameter('inCategories', $this->idsInCategories($filter->categoryIds) ?: [0]);
        }

        return $qb;
    }

    /**
     * @return list<int>
     */
    private function idsMatching(string $query): array
    {
        $ids = $this->em->getConnection()->fetchFirstColumn(
            "SELECT p.id FROM market_product p
             WHERE LOWER(p.sku) LIKE :q
                OR p.barcode LIKE :q
                OR EXISTS (SELECT 1 FROM json_each(p.texts) AS t WHERE LOWER(t.value->>'name') LIKE :q)",
            ['q' => '%' . mb_strtolower(addcslashes($query, '%_\\')) . '%'],
        );

        return array_map('intval', $ids);
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<int>
     */
    private function idsInCategories(array $categoryIds): array
    {
        $ids = $this->em->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT product_id FROM market_product_category WHERE category_id IN (:ids)',
            ['ids' => $categoryIds],
            ['ids' => ArrayParameterType::INTEGER],
        );

        return array_map('intval', $ids);
    }

    /**
     * Pone en cada producto sus categorías, leídas de la tabla de unión en una sola consulta.
     *
     * @param list<Product> $products
     */
    private function loadCategories(array $products): void
    {
        $ids = array_values(array_filter(array_map(static fn (Product $p): ?int => $p->id(), $products)));
        if ($ids === []) {
            return;
        }
        $byProduct = [];
        foreach ($this->em->getConnection()->fetchAllNumeric(
            'SELECT product_id, category_id FROM market_product_category WHERE product_id IN (:ids) ORDER BY category_id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        ) as [$productId, $categoryId]) {
            $byProduct[(int) $productId][] = (int) $categoryId;
        }
        $property = new \ReflectionProperty(Product::class, 'categoryIds');
        foreach ($products as $product) {
            $property->setValue($product, $byProduct[(int) $product->id()] ?? []);
        }
    }

    public function save(Product $product): void
    {
        $this->em->wrapInTransaction(function () use ($product): void {
            $this->em->persist($product);
            $this->em->flush();
            $this->syncCategories($product);
        });
    }

    /** Deja en la tabla de unión exactamente las categorías del producto. */
    private function syncCategories(Product $product): void
    {
        $connection = $this->em->getConnection();
        $id = (int) $product->id();
        $wanted = $product->categoryIds();
        $current = array_map('intval', $connection->fetchFirstColumn('SELECT category_id FROM market_product_category WHERE product_id = ?', [$id]));

        foreach (array_diff($current, $wanted) as $categoryId) {
            $connection->executeStatement('DELETE FROM market_product_category WHERE product_id = ? AND category_id = ?', [$id, $categoryId]);
        }
        foreach (array_diff($wanted, $current) as $categoryId) {
            $connection->executeStatement('INSERT INTO market_product_category (product_id, category_id) VALUES (?, ?)', [$id, $categoryId]);
        }
    }
}
