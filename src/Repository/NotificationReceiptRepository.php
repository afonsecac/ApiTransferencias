<?php

namespace App\Repository;

use App\Entity\NotificationReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationReceipt>
 *
 * @method NotificationReceipt|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationReceipt|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationReceipt[]    findAll()
 * @method NotificationReceipt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationReceipt::class);
    }

    public function findOneByNotificationAndUser(int $notificationId, int $userId): ?NotificationReceipt
    {
        return $this->findOneBy(['notification' => $notificationId, 'user' => $userId]);
    }

    /**
     * Recibos del usuario para un lote de notificaciones (para pintar el
     * listado sin hacer una consulta por fila).
     *
     * @param int[] $notificationIds
     *
     * @return NotificationReceipt[]
     */
    public function findByNotificationsAndUser(array $notificationIds, int $userId): array
    {
        if ($notificationIds === []) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :userId')
            ->andWhere('r.notification IN (:ids)')
            ->setParameter('userId', $userId)
            ->setParameter('ids', $notificationIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marca como leídas, de una vez, todas las notificaciones de la lista de
     * IDs (ya resuelta por NotificationRepository::findVisibleUnreadIds con
     * el predicado de visibilidad DQL, para no duplicarlo en SQL). Upsert
     * nativo para no hidratar N entidades ni pagar N round-trips.
     *
     * @param int[] $notificationIds
     */
    public function markManyRead(array $notificationIds, int $userId): int
    {
        if ($notificationIds === []) {
            return 0;
        }

        $conn = $this->getEntityManager()->getConnection();
        $marked = 0;

        // En lotes: un IN (...) de miles de IDs no aporta nada y complica el plan.
        foreach (array_chunk($notificationIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = <<<SQL
                INSERT INTO notification_receipt (notification_id, user_id, read_at)
                SELECT id, ?, NOW() FROM notification WHERE id IN ({$placeholders})
                ON CONFLICT (notification_id, user_id)
                DO UPDATE SET read_at = EXCLUDED.read_at
                WHERE notification_receipt.read_at IS NULL
                   OR notification_receipt.read_at < EXCLUDED.read_at
                SQL;

            $marked += $conn->executeStatement($sql, [$userId, ...$chunk]);
        }

        return $marked;
    }
}
