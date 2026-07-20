<?php

namespace App\Controller;

use App\DTO\TwoFactorBackupCodeDto;
use App\DTO\TwoFactorCodeDto;
use App\DTO\TwoFactorDisableDto;
use App\DTO\TwoFactorEnrollSetupDto;
use App\DTO\TwoFactorVerifyDto;
use App\DTO\Out\TwoFactorSetupOutDto;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\RefreshTokenService;
use App\Service\TwoFactorService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        #[Autowire(service: 'limiter.two_factor_code')]
        private readonly RateLimiterFactory  $twoFactorCodeLimiter,
    ) {}

    /**
     * Emite la sesión completa (JWT + cookie de refresh). Compartido por la verificación
     * y por la confirmación del enrolamiento: en ambos casos el usuario acaba de superar
     * el segundo factor, así que el resultado debe ser idéntico al de un login normal.
     */
    private function issueSession(User $user, string $clientIp, ?array $backupCodes = null): JsonResponse
    {
        $user->setCurrentIp($clientIp);
        $currentUser  = $this->userService->createPayloadUser($user);
        $accessToken  = $this->userService->createTokenFromPayload($user, $currentUser);
        $refreshToken = $this->refreshTokenService->createForUser($user, $clientIp);

        $payload = [
            'token'     => $accessToken,
            'expiresIn' => (int) $this->getParameter('app.jwt.expired') * 60,
            'user'      => $this->serializer->normalize($currentUser, 'json', ['groups' => ['profile']]),
        ];

        // Los códigos de respaldo solo viajan al activarse el 2FA: es la única vez que
        // existen en claro y el cliente debe mostrarlos para que el usuario los guarde.
        if ($backupCodes !== null) {
            $payload['backupCodes'] = $backupCodes;
        }

        $response = $this->json($payload);

        $response->headers->setCookie($this->refreshTokenService->createCookie($refreshToken));

        return $response;
    }

    /**
     * Respuesta de error del módulo 2FA. Incluye el código simbólico (`INVALID_2FA_CODE`,
     * `PENDING_TOKEN_EXPIRED`…) además del mensaje: el cliente necesita distinguir los
     * casos para decidir si limpia el código o vuelve al paso de credenciales.
     */
    private function errorResponse(MyCurrentException $e): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => $e->getMessage(), 'code' => $e->getCodeWork()]],
            $e->getCode()
        );
    }

    /** Limita los intentos de código por pendingToken o por usuario. */
    private function tooManyAttempts(string $key): bool
    {
        return !$this->twoFactorCodeLimiter->create($key)->consume()->isAccepted();
    }

    private function rateLimitResponse(): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => 'Too many attempts. Please try again later.', 'code' => 'TOO_MANY_ATTEMPTS']],
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

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
            return $this->errorResponse($e);
        }

        return $this->json($result);
    }

    #[Route('/confirm', name: 'dashboard_2fa_confirm', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Confirmar y activar el 2FA',
        description: 'Verifica el código obtenido tras llamar a `/2fa/setup` y activa el 2FA en la cuenta. Una vez activado, todos los siguientes inicios de sesión requerirán verificación 2FA. Devuelve además `backupCodes`: un juego de códigos de un solo uso que solo se muestran aquí y que permiten entrar si el usuario pierde el dispositivo. Errores posibles: `2FA_SETUP_NOT_STARTED` (llamar antes a `/2fa/setup`), `INVALID_2FA_CODE` (código incorrecto), `2FA_CODE_EXPIRED` (solo `method=email`; reiniciar el proceso).',
        tag: '2FA',
        requestDto: TwoFactorCodeDto::class,
    )]
    public function confirm(#[CurrentUser] ?User $user, TwoFactorCodeDto $dto): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->tooManyAttempts('2fa_user_' . $user->getId())) {
            return $this->rateLimitResponse();
        }

        try {
            $this->twoFactorService->confirmSetup($user, $dto->getCode());
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json([
            'twoFactorEnabled' => true,
            'backupCodes'      => $this->twoFactorService->regenerateBackupCodes($user),
        ]);
    }

    #[Route('', name: 'dashboard_2fa_disable', methods: ['DELETE'])]
    #[DashboardEndpoint(
        summary: 'Desactivar el 2FA',
        description: 'Desactiva el 2FA del usuario autenticado y elimina el secreto almacenado. Exige la contraseña actual en el cuerpo de la petición: sin ella, un token de sesión robado bastaría para apagar el segundo factor. Si la política global es `mandatory` y el deadline ya ha pasado, el próximo login pedirá configurarlo de nuevo. Devuelve `{ "twoFactorEnabled": false }`. Error posible: `INVALID_CURRENT_PASSWORD`.',
        tag: '2FA',
        requestDto: TwoFactorDisableDto::class,
    )]
    public function disable(#[CurrentUser] ?User $user, TwoFactorDisableDto $dto): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->tooManyAttempts('2fa_user_' . $user->getId())) {
            return $this->rateLimitResponse();
        }

        try {
            $this->twoFactorService->disableWithPassword($user, $dto->getPassword());
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

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

        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $user = $this->twoFactorService->verifyLoginCode(
                $dto->getPendingToken(),
                $dto->getCode()
            );
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->issueSession($user, $clientIp);
    }

    #[Route('/enroll/setup', name: 'dashboard_2fa_enroll_setup', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Iniciar el enrolamiento del 2FA durante el login',
        description: 'Primer paso cuando `/login` devuelve `requiresEnrollment=true`: el usuario debe configurar el 2FA porque la política global ya lo exige, pero todavía no lo tiene activo. Se autentica con el `pendingToken` emitido por `/login` (no hace falta JWT, que aún no existe). Si `method=totp` devuelve el secreto y la URI para el QR; si `method=email` envía un código al correo. Continuar con `/2fa/enroll/confirm`.',
        tag: '2FA',
        requestDto: TwoFactorEnrollSetupDto::class,
        responseDto: TwoFactorSetupOutDto::class,
    )]
    public function enrollSetup(TwoFactorEnrollSetupDto $dto): JsonResponse
    {
        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $result = $this->twoFactorService->startEnrollment($dto->getPendingToken());
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json($result);
    }

    #[Route('/enroll/confirm', name: 'dashboard_2fa_enroll_confirm', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Confirmar el enrolamiento y completar el login',
        description: 'Segundo paso del enrolamiento durante el login. Verifica el primer código, activa el 2FA y devuelve el JWT y el refresh token: enrolarse e iniciar sesión son una sola operación, de modo que nunca se emite una sesión sin segundo factor. Errores posibles: `INVALID_PENDING_TOKEN`, `PENDING_TOKEN_EXPIRED`, `INVALID_2FA_CODE`, `2FA_CODE_EXPIRED`, `2FA_ALREADY_ENABLED`.',
        tag: '2FA',
        requestDto: TwoFactorVerifyDto::class,
    )]
    public function enrollConfirm(TwoFactorVerifyDto $dto, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';

        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $resultado = $this->twoFactorService->confirmEnrollment(
                $dto->getPendingToken(),
                $dto->getCode()
            );
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->issueSession($resultado['user'], $clientIp, $resultado['backupCodes']);
    }

    #[Route('/recover/backup-code', name: 'dashboard_2fa_recover_backup', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Entrar con un código de respaldo',
        description: 'Permite completar el login usando uno de los códigos de respaldo entregados al activar el 2FA, para cuando el usuario ha perdido el dispositivo. El código es de un solo uso y se invalida al consumirse. Devuelve el JWT igual que `/2fa/verify`, junto con `backupCodesRemaining` para avisar al usuario de cuántos le quedan. Error posible: `INVALID_BACKUP_CODE`.',
        tag: '2FA',
        requestDto: TwoFactorBackupCodeDto::class,
    )]
    public function recoverWithBackupCode(TwoFactorBackupCodeDto $dto, Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp() ?? 'unknown';

        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $user = $this->twoFactorService->verifyBackupCode(
                $dto->getPendingToken(),
                $dto->getCode()
            );
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        $response = $this->issueSession($user, $clientIp);
        $payload  = json_decode($response->getContent(), true);
        $payload['backupCodesRemaining'] = $this->twoFactorService->countBackupCodes($user);
        $response->setData($payload);

        return $response;
    }

    #[Route('/recover/email/request', name: 'dashboard_2fa_recover_email_request', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Pedir un código por correo para reiniciar el 2FA',
        description: 'Vía de último recurso para quien ha perdido el dispositivo y los códigos de respaldo. Requiere el `pendingToken`, es decir, haber superado ya usuario y contraseña: comprometer solo el correo no permite saltarse el segundo factor. Envía un código de 6 dígitos válido 10 minutos. Continuar con `/2fa/recover/email/confirm`.',
        tag: '2FA',
        requestDto: TwoFactorEnrollSetupDto::class,
    )]
    public function requestEmailReset(TwoFactorEnrollSetupDto $dto): JsonResponse
    {
        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $this->twoFactorService->requestEmailReset($dto->getPendingToken());
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json(['message' => 'Verification code sent to your email.']);
    }

    #[Route('/recover/email/confirm', name: 'dashboard_2fa_recover_email_confirm', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Confirmar el reinicio del 2FA por correo',
        description: 'Valida el código enviado al correo y desactiva el 2FA del usuario, dejando vivo el `pendingToken` para que continúe con `/2fa/enroll/setup` y vincule su dispositivo nuevo. No emite sesión: el acceso se obtiene al terminar el enrolamiento, nunca antes. Devuelve `{ "requiresEnrollment": true }`.',
        tag: '2FA',
        requestDto: TwoFactorVerifyDto::class,
    )]
    public function confirmEmailReset(TwoFactorVerifyDto $dto): JsonResponse
    {
        if ($this->tooManyAttempts('2fa_' . $dto->getPendingToken())) {
            return $this->rateLimitResponse();
        }

        try {
            $this->twoFactorService->confirmEmailReset($dto->getPendingToken(), $dto->getCode());
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json(['requiresEnrollment' => true]);
    }

    #[Route('/backup-codes', name: 'dashboard_2fa_regenerate_backup_codes', methods: ['POST'])]
    #[DashboardEndpoint(
        summary: 'Regenerar los códigos de respaldo',
        description: 'Genera un juego nuevo de códigos de respaldo e invalida los anteriores. Se devuelven en claro una única vez: si el usuario los pierde, tendrá que volver a generarlos. Requiere tener el 2FA activo.',
        tag: '2FA',
    )]
    public function regenerateBackupCodes(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isTwoFactorEnabled()) {
            return $this->json(
                ['error' => ['message' => '2FA is not enabled.', 'code' => '2FA_NOT_ENABLED']],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json(['backupCodes' => $this->twoFactorService->regenerateBackupCodes($user)]);
    }
}
