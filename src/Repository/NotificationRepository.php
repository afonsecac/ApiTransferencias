<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\NotificationReceipt;
use App\Enums\NotificationAudienceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    /**
     * Ventana dura de retención: por diseño la bandeja nunca muestra nada más
     * antiguo que esto, independientemente de la purga programada (que puede
     * ir por detrás). Mantiene el conteo barato incluso si la purga falla.
     */
    private const RETENTION_DAYS = 90;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Construye el predicado de visibilidad de notificaciones para un usuario:
     * globales, dirigidas a él, a un rol que alcanza (jerarquía ya expandida
     * por el llamador con RoleHierarchyInterface) o al cliente al que pertenece.
     *
     * Se usa tanto para el listado paginado como para el conteo de no leídas,
     * de modo que ambos vean exactamente el mismo universo de notificaciones.
     *
     * Recibe IDs primitivos, no la entidad User: el stream SSE llama a este
     * predicado en un bucle con em->clear() en cada vuelta, y una entidad
     * capturada antes del bucle quedaría desasociada — ver
     * NotificationCenterService::pollForRaw().
     *
     * @param string[] $reachableRoles
     */
    public function applyVisibility(
        QueryBuilder $qb,
        string $alias,
        int $userId,
        ?int $clientId,
        array $reachableRoles,
        ?int $environmentId,
    ): void {
        $orX = $qb->expr()->orX(
            $qb->expr()->eq("{$alias}.audience", ':audGlobal'),
            $qb->expr()->andX(
                $qb->expr()->eq("{$alias}.audience", ':audUser'),
                $qb->expr()->eq("{$alias}.targetUser", ':uid'),
            ),
        );
        $qb->setParameter('audGlobal', NotificationAudienceEnum::GLOBAL)
            ->setParameter('audUser', NotificationAudienceEnum::USER)
            ->setParameter('uid', $userId);

        if ($reachableRoles !== []) {
            $orX->add($qb->expr()->andX(
                $qb->expr()->eq("{$alias}.audience", ':audRole'),
                $qb->expr()->in("{$alias}.targetRole", ':roles'),
            ));
            $qb->setParameter('audRole', NotificationAudienceEnum::ROLE)
                ->setParameter('roles', $reachableRoles);
        }

        if ($clientId !== null) {
            $orX->add($qb->expr()->andX(
                $qb->expr()->eq("{$alias}.audience", ':audClient'),
                $qb->expr()->eq("{$alias}.client", ':clientId'),
            ));
            $qb->setParameter('audClient', NotificationAudienceEnum::CLIENT)
                ->setParameter('clientId', $clientId);
        }

        $qb->andWhere($orX)
            ->andWhere("{$alias}.createdAt >= :retentionFloor")
            ->andWhere($qb->expr()->orX(
                "{$alias}.expiresAt IS NULL",
                "{$alias}.expiresAt > :now",
            ))
            ->setParameter('retentionFloor', new \DateTimeImmutable('-' . self::RETENTION_DAYS . ' days'))
            ->setParameter('now', new \DateTimeImmutable());

        if ($environmentId !== null) {
            $qb->andWhere($qb->expr()->orX(
                "{$alias}.environment IS NULL",
                "{$alias}.environment = :envId",
            ))->setParameter('envId', $environmentId);
        }

        // Descartar es permanente y personal: una vez con dismissedAt, la
        // notificación desaparece de este usuario para siempre, no solo del
        // conteo de no leídas — sea GLOBAL/ROLE/CLIENT y la vean otros o no.
        $subQb = $this->getEntityManager()->createQueryBuilder();
        $subQb->select('1')
            ->from(NotificationReceipt::class, 'rd')
            ->where("rd.notification = {$alias}")
            ->andWhere('rd.user = :dismissUid')
            ->andWhere('rd.dismissedAt IS NOT NULL');

        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQb->getDQL())))
            ->setParameter('dismissUid', $userId);
    }

    /**
     * @param string[] $reachableRoles
     *
     * @return Notification[]
     */
    public function findVisiblePage(
        int $userId,
        ?int $clientId,
        array $reachableRoles,
        ?int $environmentId,
        bool $onlyUnread,
        ?string $type,
        ?string $level,
        int $page,
        int $limit,
        string $orderField,
        string $orderDirection,
    ): array {
        $qb = $this->createQueryBuilder('n');
        $this->applyVisibility($qb, 'n', $userId, $clientId, $reachableRoles, $environmentId);

        if ($type !== null) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }
        if ($level !== null) {
            $qb->andWhere('n.level = :level')->setParameter('level', $level);
        }
        if ($onlyUnread) {
            $this->applyUnreadPredicate($qb, 'n', $userId);
        }

        return $qb->orderBy($orderField, $orderDirection)
            ->setFirstResult($page * $limit)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notificaciones visibles con id > $afterId, para el polling del stream.
     *
     * @param string[] $reachableRoles
     *
     * @return Notification[]
     */
    public function findVisibleSince(
        int $userId,
        ?int $clientId,
        array $reachableRoles,
        ?int $environmentId,
        int $afterId,
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.id > :afterId')
            ->setParameter('afterId', $afterId)
            ->orderBy('n.id', 'ASC');
        $this->applyVisibility($qb, 'n', $userId, $clientId, $reachableRoles, $environmentId);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $reachableRoles
     */
    public function countUnread(int $userId, ?int $clientId, array $reachableRoles, ?int $environmentId): int
    {
        $qb = $this->createQueryBuilder('n')->select('COUNT(n.id)');
        $this->applyVisibility($qb, 'n', $userId, $clientId, $reachableRoles, $environmentId);
        $this->applyUnreadPredicate($qb, 'n', $userId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * IDs de todas las notificaciones visibles y no leídas de un usuario, para
     * que "marcar todas como leídas" pueda hacer un upsert nativo (bulk) sobre
     * un conjunto de IDs concreto sin duplicar el predicado de visibilidad en
     * SQL crudo — una sola implementación (DQL) decide qué es visible.
     *
     * @param string[] $reachableRoles
     *
     * @return int[]
     */
    public function findVisibleUnreadIds(int $userId, ?int $clientId, array $reachableRoles, ?int $environmentId): array
    {
        $qb = $this->createQueryBuilder('n')->select('n.id');
        $this->applyVisibility($qb, 'n', $userId, $clientId, $reachableRoles, $environmentId);
        $this->applyUnreadPredicate($qb, 'n', $userId);

        return array_column($qb->getQuery()->getScalarResult(), 'id');
    }

    /**
     * @param string[] $reachableRoles
     */
    public function findVisibleById(int $id, int $userId, ?int $clientId, array $reachableRoles): ?Notification
    {
        $qb = $this->createQueryBuilder('n')->andWhere('n.id = :id')->setParameter('id', $id);
        $this->applyVisibility($qb, 'n', $userId, $clientId, $reachableRoles, null);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Cuenta lo que purgeExpired() borraría, para --dry-run.
     */
    public function countExpired(int $retentionDays): int
    {
        $retentionFloor = new \DateTimeImmutable('-' . $retentionDays . ' days');
        $expiredFloor = new \DateTimeImmutable('-7 days');

        $qb = $this->createQueryBuilder('n')->select('COUNT(n.id)');
        $qb->andWhere($qb->expr()->orX(
            'n.expiresAt IS NOT NULL AND n.expiresAt < :expiredFloor',
            'n.createdAt < :retentionFloor',
        ))
            ->setParameter('expiredFloor', $expiredFloor)
            ->setParameter('retentionFloor', $retentionFloor);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Purga en lotes (para no tomar locks largos): caducadas hace más de una
     * semana, o fuera de la ventana de retención. Devuelve el total borrado.
     * Los umbrales se calculan en PHP y se pasan como timestamps, para no
     * depender de construir un INTERVAL a partir de un parámetro ligado.
     */
    public function purgeExpired(int $retentionDays, int $batchSize): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $deleted = 0;

        $retentionFloor = new \DateTimeImmutable('-' . $retentionDays . ' days');
        $expiredFloor = new \DateTimeImmutable('-7 days');

        $sql = <<<'SQL'
            DELETE FROM notification
            WHERE id IN (
                SELECT id FROM notification
                WHERE (expires_at IS NOT NULL AND expires_at < ?)
                   OR created_at < ?
                LIMIT ?
            )
            SQL;

        do {
            $affected = $conn->executeStatement($sql, [
                $expiredFloor->format('Y-m-d H:i:s'),
                $retentionFloor->format('Y-m-d H:i:s'),
                $batchSize,
            ], [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::INTEGER,
            ]);
            $deleted += $affected;
        } while ($affected > 0);

        return $deleted;
    }

    /**
     * Excluye lo ya leído. Lo descartado ya ha quedado fuera por
     * applyVisibility(), así que aquí basta con el estado de lectura.
     *
     * La comparación read_at >= updated_at (no "read_at IS NOT NULL") es
     * deliberada: un digest agrupado (bumpGroup) que vuelve a incrementarse
     * debe reaparecer como no leído.
     */
    private function applyUnreadPredicate(QueryBuilder $qb, string $alias, int $userId): void
    {
        $subQb = $this->getEntityManager()->createQueryBuilder();
        $subQb->select('1')
            ->from(NotificationReceipt::class, 'r')
            ->where("r.notification = {$alias}")
            ->andWhere('r.user = :readUid')
            ->andWhere("r.readAt >= {$alias}.updatedAt");

        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQb->getDQL())))
            ->setParameter('readUid', $userId);
    }
}
