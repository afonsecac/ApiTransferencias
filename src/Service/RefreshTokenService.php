<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenService
{
    public const COOKIE_NAME = '__Secure-RefreshToken';
    public const TOKEN_LIFETIME_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Crea un nuevo refresh token para el usuario (login inicial).
     */
    public function createForUser(User $user, string $ip): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setToken($this->generateToken());
        $refreshToken->setFamily($this->generateFamily());
        $refreshToken->setOriginIp($ip);
        $refreshToken->setExpiresAt(
            (new \DateTimeImmutable('now'))->modify('+' . self::TOKEN_LIFETIME_DAYS . ' days')
        );

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    /**
     * Rota el refresh token: revoca el actual, crea uno nuevo en la misma familia.
     * Si el token ya fue revocado (reutilización), invalida TODA la familia.
     */
    public function rotate(string $tokenValue, string $ip): ?RefreshToken
    {
        // Buscar el token (incluyendo revocados para detectar reutilización)
        $existing = $this->em->getRepository(RefreshToken::class)->findOneBy(['token' => $tokenValue]);

        if ($existing === null) {
            return null;
        }

        // Token ya revocado = posible robo. Invalidar toda la familia.
        if ($existing->getRevokedAt() !== null) {
            $this->logger->warning('Refresh token reuse detected, revoking family', [
                'family' => $existing->getFamily(),
                'userId' => $existing->getUser()->getId(),
                'ip' => $ip,
            ]);
            $this->refreshTokenRepository->revokeFamily($existing->getFamily());
            return null;
        }

        // Token expirado
        if (!$existing->isValid()) {
            return null;
        }

        // Revocar el token actual
        $existing->setRevokedAt(new \DateTimeImmutable('now'));

        // Crear nuevo token en la misma familia
        $newToken = new RefreshToken();
        $newToken->setUser($existing->getUser());
        $newToken->setToken($this->generateToken());
        $newToken->setFamily($existing->getFamily());
        $newToken->setOriginIp($ip);
        $newToken->setExpiresAt(
            (new \DateTimeImmutable('now'))->modify('+' . self::TOKEN_LIFETIME_DAYS . ' days')
        );

        $this->em->persist($newToken);
        $this->em->flush();

        return $newToken;
    }

    /**
     * Revoca todos los refresh tokens del usuario (logout).
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->refreshTokenRepository->revokeAllForUser($userId);
    }

    /**
     * Crea la cookie HttpOnly para el refresh token.
     */
    public function createCookie(RefreshToken $refreshToken): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($refreshToken->getToken())
            ->withExpires($refreshToken->getExpiresAt())
            ->withPath('/dashboard/api')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('strict');
    }

    /**
     * Crea una cookie vacía que borra el refresh token del navegador.
     */
    public function createExpiredCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/dashboard/api')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('strict');
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    private function generateFamily(): string
    {
        return bin2hex(random_bytes(32));
    }
}
