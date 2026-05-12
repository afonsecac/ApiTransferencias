<?php

namespace App\Controller;

use App\DTO\TwoFactorCodeDto;
use App\DTO\TwoFactorVerifyDto;
use App\DTO\Out\TwoFactorSetupOutDto;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\RefreshTokenService;
use App\Service\TwoFactorService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService    $twoFactorService,
        private readonly UserService         $userService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly NormalizerInterface $serializer,
    ) {}

    #[Route('/setup', name: 'dashboard_2fa_setup', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Iniciar configuración del 2FA',
        description: 'Inicia el proceso de activación según el método global configurado. Si `method=totp`, devuelve el secreto Base32 y la URI para generar el QR con una app autenticadora (Google Authenticator, Authy…). Si `method=email`, envía un código de 6 dígitos al correo del usuario. Llama a `/2fa/confirm` con el código para completar la activación.',
        tag: '2FA',
        responseDto: TwoFactorSetupOutDto::class,
    )]
    public function setup(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorEnabled()) {
            return $this->json(['error' => ['message' => '2FA is already enabled.']], Response::HTTP_CONFLICT);
        }

        try {
            $result = $this->twoFactorService->startSetup($user);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($result);
    }

    #[Route('/confirm', name: 'dashboard_2fa_confirm', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Confirmar y activar el 2FA',
        description: 'Verifica el código obtenido tras llamar a `/2fa/setup` y activa el 2FA en la cuenta. Una vez activado, todos los siguientes inicios de sesión requerirán verificación 2FA. Errores posibles: `2FA_SETUP_NOT_STARTED` (llamar antes a `/2fa/setup`), `INVALID_2FA_CODE` (código incorrecto), `2FA_CODE_EXPIRED` (solo `method=email`; reiniciar el proceso).',
        tag: '2FA',
        requestDto: TwoFactorCodeDto::class,
    )]
    public function confirm(#[CurrentUser] ?User $user, TwoFactorCodeDto $dto): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->twoFactorService->confirmSetup($user, $dto->getCode());
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json(['twoFactorEnabled' => true]);
    }

    #[Route('', name: 'dashboard_2fa_disable', methods: ['DELETE'])]
    #[DashboardEndpoint(
        summary: 'Desactivar el 2FA',
        description: 'Desactiva el 2FA del usuario autenticado y elimina el secreto almacenado. Si la política global es `mandatory` y el deadline ya ha pasado, el próximo login volverá a requerir 2FA igualmente (se generará un código nuevo). Devuelve `{ "twoFactorEnabled": false }`.',
        tag: '2FA',
    )]
    public function disable(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        $this->twoFactorService->disable($user);

        return $this->json(['twoFactorEnabled' => false]);
    }

    #[Route('/verify', name: 'dashboard_2fa_verify', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Verificar código 2FA y completar el login',
        description: 'Segundo paso del login cuando `/login` devuelve `requires2fa=true`. Recibe el `pendingToken` y el código de verificación; si son válidos, devuelve el JWT y el refresh token igual que un login normal. El `pendingToken` expira en 10 minutos. Errores posibles: `INVALID_PENDING_TOKEN`, `PENDING_TOKEN_EXPIRED`, `INVALID_2FA_CODE`, `2FA_CODE_EXPIRED`.',
        tag: '2FA',
        requestDto: TwoFactorVerifyDto::class,
    )]
    public function verify(TwoFactorVerifyDto $dto, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';

        try {
            $user = $this->twoFactorService->verifyLoginCode(
                $dto->getPendingToken(),
                $dto->getCode()
            );
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        $user->setCurrentIp($clientIp);
        $currentUser  = $this->userService->createPayloadUser($user);
        $accessToken  = $this->userService->createTokenFromPayload($user, $currentUser);
        $refreshToken = $this->refreshTokenService->createForUser($user, $clientIp);

        $response = $this->json([
            'token'     => $accessToken,
            'expiresIn' => (int) $this->getParameter('app.jwt.expired') * 60,
            'user'      => $this->serializer->normalize($currentUser, 'json', ['groups' => ['profile']]),
        ]);

        $response->headers->setCookie($this->refreshTokenService->createCookie($refreshToken));

        return $response;
    }
}
