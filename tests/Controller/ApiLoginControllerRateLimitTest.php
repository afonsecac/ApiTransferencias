<?php

namespace App\Tests\Controller;

use App\Controller\ApiLoginController;
use App\Service\RefreshTokenService;
use App\Service\TwoFactorService;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Hallazgo 3 del pentest 2026-10-09: limiter.dashboard_login se consumía
 * DESPUÉS de resolver #[CurrentUser], que es null en cualquier intento con
 * contraseña incorrecta — así que nunca se alcanzaba el consume() en un
 * fallo real. Solo protegía el límite genérico por IP (20/5min).
 *
 * @covers \App\Controller\ApiLoginController
 */
class ApiLoginControllerRateLimitTest extends TestCase
{
    private function buildController(): ApiLoginController
    {
        // limit: 5, interval: 1 minute (mismo valor que config/packages/framework.yaml)
        $dashboardLoginLimiter = new RateLimiterFactory(
            ['id' => 'dashboard_login', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 minute'],
            new InMemoryStorage()
        );
        // limit alto a propósito: este test aísla el limitador por usuario, no el de IP.
        $dashboardLoginIpLimiter = new RateLimiterFactory(
            ['id' => 'dashboard_login_ip', 'policy' => 'sliding_window', 'limit' => 1000, 'interval' => '5 minutes'],
            new InMemoryStorage()
        );
        $unusedLimiter = new RateLimiterFactory(
            ['id' => 'unused', 'policy' => 'fixed_window', 'limit' => 1000, 'interval' => '15 minutes'],
            new InMemoryStorage()
        );

        $controller = new ApiLoginController(
            $this->createMock(UserService::class),
            $this->createMock(NormalizerInterface::class),
            $this->createMock(RefreshTokenService::class),
            $this->createMock(TwoFactorService::class),
            $dashboardLoginLimiter,
            $dashboardLoginIpLimiter,
            $unusedLimiter,
            $unusedLimiter,
            $unusedLimiter,
        );

        // AbstractController::json() mira $this->container->has('serializer');
        // sin container inicializado, el acceso a la property typed explota.
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        return $controller;
    }

    private function wrongPasswordRequest(string $email): Request
    {
        return Request::create(
            '/dashboard/api/login',
            'POST',
            server: ['REMOTE_ADDR' => '203.0.113.5'],
            content: json_encode(['username' => $email, 'password' => 'wrong-password'])
        );
    }

    public function testSixFailedAttemptsForTheSameEmailAreRateLimitedEvenThoughCurrentUserIsNull(): void
    {
        $controller = $this->buildController();
        $email = 'victim@example.com';

        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            // #[CurrentUser] resuelve null en CUALQUIER intento con contraseña
            // incorrecta — es exactamente lo que probamos: el límite debe
            // dispararse igual, sin depender de que el usuario exista/resuelva.
            $responses[] = $controller->index(null, $this->wrongPasswordRequest($email));
        }

        $statusCodes = array_map(fn (Response $r) => $r->getStatusCode(), $responses);

        // Los primeros 5 intentos (limit configurado) deben llegar a la
        // comprobación de credenciales (401, no 429); el 6º debe quedar
        // bloqueado por el limitador por email ANTES de esa comprobación.
        self::assertSame(
            [401, 401, 401, 401, 401, 429],
            $statusCodes,
            'El 6º intento con la misma contraseña incorrecta debería devolver 429 (limiter.dashboard_login por email), no 401.'
        );
    }

    public function testFailedAttemptsForDifferentEmailsDoNotShareTheLimit(): void
    {
        $controller = $this->buildController();

        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            // Email distinto en cada intento: no deben compartir cupo.
            $responses[] = $controller->index(null, $this->wrongPasswordRequest("user{$i}@example.com"));
        }

        $statusCodes = array_map(fn (Response $r) => $r->getStatusCode(), $responses);

        self::assertSame([401, 401, 401, 401, 401, 401], $statusCodes);
    }
}
