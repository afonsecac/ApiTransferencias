<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByToken(string $token): ?RefreshToken
    {
        $now = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Revoca todos los tokens de una familia (detección de robo).
     */
    public function revokeFamily(string $family): void
    {
        $now = new \DateTimeImmutable('now');

        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->where('rt.family = :family')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('family', $family)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * Revoca todos los tokens activos de un usuario.
     */
    public function revokeAllForUser(int $userId): void
    {
        $now = new \DateTimeImmutable('now');

        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->where('IDENTITY(rt.user) = :userId')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * Limpia tokens expirados (para cron de mantenimiento).
     */
    public function purgeExpired(): int
    {
        $now = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
