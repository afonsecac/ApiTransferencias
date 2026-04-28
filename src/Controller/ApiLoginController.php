<?php

namespace App\Controller;

use App\DTO\ForgotPassword;
use App\DTO\Out\AuthTokenOutDto;
use App\DTO\Out\LogoutOutDto;
use App\DTO\ResetPassword;
use App\Entity\User;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\RefreshTokenService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiLoginController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly NormalizerInterface $serializer,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RateLimiterFactory $dashboardLoginLimiter,
        private readonly RateLimiterFactory $dashboardLoginIpLimiter,
        private readonly RateLimiterFactory $forgotPasswordLimiter,
        private readonly RateLimiterFactory $resetPasswordLimiter,
    ) {
    }

    #[Route('/login', name: 'app_dashboard_login', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Login', tag: 'Auth', responseDto: AuthTokenOutDto::class)]
    public function index(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';

        // Rate limit por IP
        $ipLimiter = $this->dashboardLoginIpLimiter->create($clientIp);
        if (!$ipLimiter->consume()->isAccepted()) {
            return $this->json([
                'error' => ['message' => 'Too many login attempts. Please try again later.'],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (is_null($user)) {
            return $this->json([
                'error' => ['message' => 'Not valid credentials'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Rate limit por usuario
        $userLimiter = $this->dashboardLoginLimiter->create('user_' . $user->getId());
        if (!$userLimiter->consume()->isAccepted()) {
            return $this->json([
                'error' => ['message' => 'Too many login attempts. Please try again later.'],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user->setCurrentIp($clientIp);
        $currentUser = $this->userService->createPayloadUser($user);
        $accessToken = $this->userService->createTokenFromPayload($user, $currentUser);

        // Crear refresh token y cookie
        $refreshToken = $this->refreshTokenService->createForUser($user, $clientIp);

        $response = $this->json([
            'token' => $accessToken,
            'expiresIn' => (int) $this->getParameter('app.jwt.expired') * 60,
            'user' => $this->serializer->normalize($currentUser, 'json', [
                'groups' => ['profile'],
            ]),
        ]);

        $response->headers->setCookie($this->refreshTokenService->createCookie($refreshToken));

        return $response;
    }

    #[Route('/refresh', name: 'app_dashboard_refresh', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Refrescar token', tag: 'Auth', responseDto: AuthTokenOutDto::class)]
    public function refresh(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $cookieToken = $request->cookies->get(RefreshTokenService::COOKIE_NAME);

        if (empty($cookieToken)) {
            return $this->json([
                'error' => ['message' => 'Missing refresh token.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Rotar el refresh token (invalida el anterior, crea uno nuevo)
        $newRefreshToken = $this->refreshTokenService->rotate($cookieToken, $clientIp);

        if ($newRefreshToken === null) {
            // Token inválido, expirado o reutilizado → borrar cookie
            $response = $this->json([
                'error' => ['message' => 'Invalid or expired refresh token.'],
            ], Response::HTTP_UNAUTHORIZED);
            $response->headers->setCookie($this->refreshTokenService->createExpiredCookie());
            return $response;
        }

        $user = $newRefreshToken->getUser();
        $user->setCurrentIp($clientIp);
        $currentUser = $this->userService->createPayloadUser($user);
        $accessToken = $this->userService->createTokenFromPayload($user, $currentUser);

        $response = $this->json([
            'token' => $accessToken,
            'expiresIn' => (int) $this->getParameter('app.jwt.expired') * 60,
            'user' => $this->serializer->normalize($currentUser, 'json', [
                'groups' => ['profile'],
            ]),
        ]);

        $response->headers->setCookie($this->refreshTokenService->createCookie($newRefreshToken));

        return $response;
    }

    #[Route('/forgot-password', name: 'app_dashboard_reset', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Solicitar recuperación de contraseña', tag: 'Auth', requestDto: ForgotPassword::class)]
    public function forgot(ForgotPassword $forgotPassword, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $limiter = $this->forgotPasswordLimiter->create($clientIp);
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => ['message' => 'Too many requests. Please try again later.'],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $response = $this->userService->forgotPassword($forgotPassword);

        return $this->json($response, $response['status'] ?? Response::HTTP_OK);
    }

    #[Route('/reset-password', name: 'app_dashboard_reset_password', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Restablecer contraseña', tag: 'Auth', requestDto: ResetPassword::class)]
    public function reset(ResetPassword $resetPassword, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $limiter = $this->resetPasswordLimiter->create($clientIp);
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => ['message' => 'Too many requests. Please try again later.'],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $response = $this->userService->resetPassword($resetPassword);
        return $this->json($response, $response['status'] ?? Response::HTTP_OK);
    }

    #[Route('/logout', name: 'app_dashboard_logout', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Logout', tag: 'Auth', responseDto: LogoutOutDto::class)]
    public function logout(Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Revocar todos los refresh tokens del usuario
        if ($user instanceof User) {
            $this->refreshTokenService->revokeAllForUser($user->getId());
            $this->userService->closeMySession($user->getId());
        }

        $response = $this->json(['logout' => true]);
        $response->headers->setCookie($this->refreshTokenService->createExpiredCookie());

        return $response;
    }
}
