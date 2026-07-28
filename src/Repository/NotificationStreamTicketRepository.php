<?php

namespace App\Repository;

use App\Entity\NotificationStreamTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationStreamTicket>
 *
 * @method NotificationStreamTicket|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationStreamTicket|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationStreamTicket[]    findAll()
 * @method NotificationStreamTicket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationStreamTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationStreamTicket::class);
    }

    public function findByTokenHash(string $tokenHash): ?NotificationStreamTicket
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Streams con un ticket consumido hace poco: proxy barato de "conexión
     * probablemente todavía abierta", usado por la guardia de concurrencia
     * del endpoint /stream (no hay forma más barata de contar sockets SSE
     * abiertos sin un registro explícito en PHP-FPM).
     */
    public function countRecentlyUsed(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.usedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Cuenta lo que purgeExpired() borraría, para --dry-run.
     */
    public function countExpired(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.expiresAt < :floor')
            ->setParameter('floor', new \DateTimeImmutable('-1 hour'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tickets caducados hace más de una hora: ya no sirven ni para auditar
     * un intento de reconexión tardío.
     */
    public function purgeExpired(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt < :floor')
            ->setParameter('floor', new \DateTimeImmutable('-1 hour'))
            ->getQuery()
            ->execute();
    }
}
