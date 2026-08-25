<?php

namespace App\Repository;

use App\Entity\CommunicationPromotions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPromotions>
 *
 * @method CommunicationPromotions|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPromotions|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPromotions[]    findAll()
 * @method CommunicationPromotions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPromotionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPromotions::class);
    }

    private const SORTABLE_FIELDS = [
        'id' => 'p.id',
        'name' => 'p.name',
        'description' => 'p.description',
        'startAt' => 'p.startAt',
        'endAt' => 'p.endAt',
        'createdAt' => 'p.createdAt',
        'updatedAt' => 'p.updatedAt',
        'environment' => 'e.type',
    ];

    public function findAllPaginated(
        int $page = 0,
        int $limit = 20,
        array $filters = [],
        string $orderBy = 'id DESC'
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.environment', 'e');

        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['environmentId'])) {
            $qb->andWhere('e.id = :envId')
                ->setParameter('envId', $filters['environmentId']);
        }
        if (isset($filters['active']) && $filters['active'] === 'true') {
            $now = new \DateTimeImmutable('now');
            $qb->andWhere('p.startAt <= :now AND p.endAt > :now')
                ->setParameter('now', $now);
        }

        $orderParts = explode(' ', $orderBy);
        $fieldKey = $orderParts[0];
        $field = self::SORTABLE_FIELDS[$fieldKey] ?? 'p.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)
            ->setFirstResult($page * $limit)
            ->getQuery()
            ->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return [
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => $results,
        ];
    }

}
