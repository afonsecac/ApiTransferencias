<?php

namespace App\Repository;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSaleInfo>
 *
 * @method CommunicationSaleInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSaleInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSaleInfo[]    findAll()
 * @method CommunicationSaleInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSaleInfo::class);
    }

    /**
     * @return CommunicationSaleInfo[]
     * @throws \DateMalformedStringException
     */
    public function getLastPending(): array
    {
        $currentDate = (new \DateTimeImmutable('now'))->modify('-5 seconds');
        return $this->createQueryBuilder('csi')
            ->andWhere('csi.state = :status')
            ->setParameter('status', CommunicationStateEnum::PENDING)
            ->getQuery()->execute();
    }

    // ── Statistics helpers ──────────────────────────────────────────

    private function applyCommonFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): void {
        if ($clientId !== null) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
        }
        if ($dateFrom !== null) {
            $qb->andWhere('s.createdAt >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo !== null) {
            $qb->andWhere('s.createdAt <= :dateTo')->setParameter('dateTo', $dateTo);
        }
        if ($environmentType !== null) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $environmentType);
        }
        if ($type === 'recharge') {
            $qb->andWhere('s INSTANCE OF App\Entity\CommunicationSaleRecharge');
        } elseif ($type === 'sale') {
            $qb->andWhere('s INSTANCE OF App\Entity\CommunicationSalePackage');
        }
    }

    private function appendNativeSqlFilters(
        string &$sql,
        array &$params,
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): void {
        if ($clientId !== null) {
            $sql .= ' AND c.id = :clientId';
            $params['clientId'] = $clientId;
        }
        if ($dateFrom !== null) {
            $sql .= ' AND s.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom->format('Y-m-d H:i:s');
        }
        if ($dateTo !== null) {
            $sql .= ' AND s.created_at <= :dateTo';
            $params['dateTo'] = $dateTo->format('Y-m-d H:i:s');
        }
        if ($environmentType !== null) {
            $sql .= ' AND e.type = :envType';
            $params['envType'] = $environmentType;
        }
        if ($type === 'recharge') {
            $sql .= " AND s.type = 'recharge'";
        } elseif ($type === 'sale') {
            $sql .= " AND s.type = 'sale'";
        }
    }

    private function baseNativeSql(): string
    {
        return 'FROM communication_sale_info s'
            . ' LEFT JOIN account a ON s.tenant_id = a.id'
            . ' LEFT JOIN client c ON a.client_id = c.id'
            . ' LEFT JOIN environment e ON a.environment_id = e.id'
            . ' WHERE 1=1';
    }

    // ── Statistics queries ──────────────────────────────────────────

    public function getStatsSummary(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e')
            ->select('COUNT(s.id) AS totalOperations')
            ->addSelect("SUM(CASE WHEN s.state = 'Completed' THEN 1 ELSE 0 END) AS completed")
            ->addSelect("SUM(CASE WHEN s.state = 'Failed' THEN 1 ELSE 0 END) AS failed")
            ->addSelect("SUM(CASE WHEN s.state = 'Pending' THEN 1 ELSE 0 END) AS pending")
            ->addSelect("SUM(CASE WHEN s.state = 'Rejected' THEN 1 ELSE 0 END) AS rejected")
            ->addSelect("SUM(CASE WHEN s.state = 'Created' THEN 1 ELSE 0 END) AS created")
            ->addSelect("SUM(CASE WHEN s.state = 'Reserved' THEN 1 ELSE 0 END) AS reserved")
            ->addSelect('SUM(s.totalPrice) AS totalAmount')
            ->addSelect('AVG(s.totalPrice) AS avgAmount');

        $this->applyCommonFilters($qb, $clientId, $dateFrom, $dateTo, $environmentType, $type);

        $row = $qb->getQuery()->getSingleResult();
        $total = (int) $row['totalOperations'];
        $completedCount = (int) $row['completed'];

        return [
            'totalOperations' => $total,
            'completed'       => $completedCount,
            'failed'          => (int) $row['failed'],
            'pending'         => (int) $row['pending'],
            'rejected'        => (int) $row['rejected'],
            'created'         => (int) $row['created'],
            'reserved'        => (int) $row['reserved'],
            'successRate'     => $total > 0 ? round(($completedCount / $total) * 100, 2) : 0,
            'totalAmount'     => round((float) ($row['totalAmount'] ?? 0), 2),
            'avgAmount'       => round((float) ($row['avgAmount'] ?? 0), 2),
        ];
    }

    public function getStatsOperationsByClient(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e')
            ->select('c.id AS clientId')
            ->addSelect('c.companyName AS clientName')
            ->addSelect('COUNT(s.id) AS total')
            ->addSelect("SUM(CASE WHEN s.state = 'Completed' THEN 1 ELSE 0 END) AS completed")
            ->addSelect("SUM(CASE WHEN s.state = 'Failed' THEN 1 ELSE 0 END) AS failed")
            ->addSelect("SUM(CASE WHEN s.state = 'Pending' THEN 1 ELSE 0 END) AS pending")
            ->addSelect("SUM(CASE WHEN s.state = 'Rejected' THEN 1 ELSE 0 END) AS rejected")
            ->addSelect("SUM(CASE WHEN s.state = 'Created' THEN 1 ELSE 0 END) AS created")
            ->addSelect("SUM(CASE WHEN s.state = 'Reserved' THEN 1 ELSE 0 END) AS reserved")
            ->addSelect('SUM(s.totalPrice) AS totalAmount')
            ->groupBy('c.id')
            ->addGroupBy('c.companyName')
            ->orderBy('total', 'DESC');

        $this->applyCommonFilters($qb, $clientId, $dateFrom, $dateTo, $environmentType, $type);

        return array_map(fn(array $row) => [
            'clientId'    => (int) $row['clientId'],
            'clientName'  => $row['clientName'],
            'total'       => (int) $row['total'],
            'completed'   => (int) $row['completed'],
            'failed'      => (int) $row['failed'],
            'pending'     => (int) $row['pending'],
            'rejected'    => (int) $row['rejected'],
            'created'     => (int) $row['created'],
            'reserved'    => (int) $row['reserved'],
            'totalAmount' => round((float) ($row['totalAmount'] ?? 0), 2),
        ], $qb->getQuery()->getScalarResult());
    }

    public function getStatsOperationsOverTime(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
        string $groupBy = 'day',
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $dateExpr = match ($groupBy) {
            'week'  => "DATE_TRUNC('week', s.created_at)::date",
            'month' => "DATE_TRUNC('month', s.created_at)::date",
            default => 'CAST(s.created_at AS DATE)',
        };

        $sql = "SELECT {$dateExpr} AS period,"
            . " COUNT(s.id) AS total,"
            . " SUM(CASE WHEN s.state = 'Completed' THEN 1 ELSE 0 END) AS completed,"
            . " SUM(CASE WHEN s.state = 'Failed' THEN 1 ELSE 0 END) AS failed,"
            . " SUM(s.total_price) AS amount "
            . $this->baseNativeSql();

        $params = [];
        $this->appendNativeSqlFilters($sql, $params, $clientId, $dateFrom, $dateTo, $environmentType, $type);
        $sql .= ' GROUP BY period ORDER BY period ASC';

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return [
            'groupBy' => $groupBy,
            'series'  => array_map(fn(array $r) => [
                'period'    => $r['period'] instanceof \DateTimeInterface
                    ? $r['period']->format('Y-m-d')
                    : (string) $r['period'],
                'total'     => (int) $r['total'],
                'completed' => (int) $r['completed'],
                'failed'    => (int) $r['failed'],
                'amount'    => round((float) ($r['amount'] ?? 0), 2),
            ], $rows),
        ];
    }

    public function getStatsBusiestDays(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT EXTRACT(ISODOW FROM s.created_at)::int AS day_of_week,'
            . ' COUNT(s.id) AS total '
            . $this->baseNativeSql();

        $params = [];
        $this->appendNativeSqlFilters($sql, $params, $clientId, $dateFrom, $dateTo, $environmentType, $type);
        $sql .= ' GROUP BY day_of_week ORDER BY total DESC';

        $dayNames = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        ];

        return array_map(fn(array $r) => [
            'dayOfWeek' => (int) $r['day_of_week'],
            'dayName'   => $dayNames[(int) $r['day_of_week']] ?? 'Unknown',
            'total'     => (int) $r['total'],
        ], $conn->executeQuery($sql, $params)->fetchAllAssociative());
    }

    public function getStatsPeakHours(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT EXTRACT(HOUR FROM s.created_at)::int AS hour,'
            . ' COUNT(s.id) AS total '
            . $this->baseNativeSql();

        $params = [];
        $this->appendNativeSqlFilters($sql, $params, $clientId, $dateFrom, $dateTo, $environmentType, $type);
        $sql .= ' GROUP BY hour ORDER BY total DESC';

        return array_map(fn(array $r) => [
            'hour'  => (int) $r['hour'],
            'total' => (int) $r['total'],
        ], $conn->executeQuery($sql, $params)->fetchAllAssociative());
    }

    public function getStatsTopPackages(
        ?int $clientId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        ?string $environmentType,
        ?string $type,
        int $limit = 10,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e')
            ->leftJoin('s.package', 'p')
            ->select('p.id AS packageId')
            ->addSelect('p.name AS packageName')
            ->addSelect('COUNT(s.id) AS total')
            ->addSelect('SUM(s.totalPrice) AS totalAmount')
            ->groupBy('p.id')
            ->addGroupBy('p.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        $this->applyCommonFilters($qb, $clientId, $dateFrom, $dateTo, $environmentType, $type);

        return array_map(fn(array $r) => [
            'packageId'   => (int) $r['packageId'],
            'packageName' => $r['packageName'],
            'total'       => (int) $r['total'],
            'totalAmount' => round((float) ($r['totalAmount'] ?? 0), 2),
        ], $qb->getQuery()->getScalarResult());
    }
}
