<?php

namespace App\Service;

use App\DTO\TwoFactorConfigDto;
use App\Entity\SysConfig;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Message\TwoFactorEmailCodeMessage;
use App\Message\TwoFactorMandatoryNotificationMessage;
use App\Repository\SysConfigRepository;
use App\Security\SecretCipher;
use App\Security\TwoFactorHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TwoFactorService
{
    private const KEY_MODE     = '2fa.mode';
    private const KEY_METHOD   = '2fa.method';
    private const KEY_DEADLINE = '2fa.deadline';

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly SysConfigRepository         $sysConfigRepo,
        private readonly MessageBusInterface         $bus,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SecretCipher                $cipher,
        private readonly string                      $appEnv,
    ) {}

    /**
     * Secreto TOTP en claro. Se guarda cifrado en base de datos, así que todo acceso al
     * secreto debe pasar por aquí en lugar de leer la propiedad directamente.
     */
    private function plainSecret(User $user): ?string
    {
        $stored = $user->getTwoFactorSecret();

        return $stored === null ? null : $this->cipher->decrypt($stored);
    }

    public function getConfig(): array
    {
        return [
            'mode'     => $this->readValue(self::KEY_MODE,     'optional'),
            'method'   => $this->readValue(self::KEY_METHOD,   'totp'),
            'deadline' => $this->readValue(self::KEY_DEADLINE, null),
        ];
    }

    /** @throws MyCurrentException */
    public function updateConfig(TwoFactorConfigDto $dto): array
    {
        if ($dto->getMode()     !== null) { $this->writeValue(self::KEY_MODE,     $dto->getMode()); }
        if ($dto->getMethod()   !== null) { $this->writeValue(self::KEY_METHOD,   $dto->getMethod()); }
        if ($dto->getDeadline() !== null) { $this->writeValue(self::KEY_DEADLINE, $dto->getDeadline()); }

        $newMode     = $this->readValue(self::KEY_MODE, 'optional');
        $newDeadline = $this->readValue(self::KEY_DEADLINE, null);

        if ($newMode === 'mandatory' && $newDeadline !== null) {
            $this->dispatchMandatoryNotices($newDeadline);
        }

        return $this->getConfig();
    }

    /** @throws MyCurrentException */
    public function startSetup(User $user): array
    {
        $method = $this->readValue(self::KEY_METHOD, 'totp');

        if ($method === 'totp') {
            $secret = TwoFactorHelper::generateSecret();
            // En base de datos va cifrado; al cliente se le devuelve en claro una única
            // vez, que es cuando lo necesita para vincular la app autenticadora.
            $user->setTwoFactorSecret($this->cipher->encrypt($secret));
            $this->em->flush();

            return [
                'method' => 'totp',
                'secret' => $secret,
                'otpUri' => TwoFactorHelper::generateTotpUri($secret, $user->getEmail(), $this->buildIssuer($user)),
            ];
        }

        $code = TwoFactorHelper::generateEmailCode();
        $user->setTwoFactorEmailCode($code);
        $user->setTwoFactorEmailCodeExpiresAt(new DateTimeImmutable('+10 minutes'));
        $this->em->flush();

        $brand = strtolower($user->getCompany()?->getContractWith() ?? 'comremit');
        $this->bus->dispatch(new TwoFactorEmailCodeMessage(
            $user->getEmail(),
            $user->getFirstName(),
            $code,
            $brand
        ));

        return ['method' => 'email', 'message' => 'Verification code sent to your email.'];
    }

    /** @throws MyCurrentException */
    public function confirmSetup(User $user, string $code): void
    {
        $method = $this->readValue(self::KEY_METHOD, 'totp');

        if ($method === 'totp') {
            $secret = $this->plainSecret($user);
            if ($secret === null) {
                throw new MyCurrentException('2FA_SETUP_NOT_STARTED', '2FA setup not started.', 400);
            }
            $this->consumeTotpCode($user, $secret, $code);
        } else {
            $storedCode = $user->getTwoFactorEmailCode();
            $expiresAt  = $user->getTwoFactorEmailCodeExpiresAt();
            if ($storedCode === null || $expiresAt === null) {
                throw new MyCurrentException('2FA_SETUP_NOT_STARTED', '2FA setup not started.', 400);
            }
            if (new DateTimeImmutable() > $expiresAt) {
                throw new MyCurrentException('2FA_CODE_EXPIRED', 'Verification code expired. Please restart setup.', 422);
            }
            if (!hash_equals($storedCode, $code)) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
        }

        $user->setTwoFactorEnabled(true);
        $user->setTwoFactorEmailCode(null);
        $user->setTwoFactorEmailCodeExpiresAt(null);
        $this->em->flush();
    }

    /**
     * Genera un juego nuevo de códigos de respaldo y devuelve los códigos en claro. Se
     * guardan hasheados, así que esta es la única ocasión en que pueden mostrarse: si el
     * usuario los pierde, hay que regenerarlos.
     *
     * @return string[]
     */
    public function regenerateBackupCodes(User $user): array
    {
        $codigos = TwoFactorHelper::generateBackupCodes();

        $user->setTwoFactorBackupCodes(
            array_map(static fn (string $c): string => TwoFactorHelper::hashBackupCode($c), $codigos)
        );
        $this->em->flush();

        return $codigos;
    }

    /**
     * Consume un código de respaldo para completar el login cuando el usuario ha perdido
     * el dispositivo. El código se elimina al usarse: es de un solo uso.
     *
     * @throws MyCurrentException
     */
    public function verifyBackupCode(string $pendingToken, string $code): User
    {
        $user = $this->resolvePendingToken($pendingToken);

        $almacenados = $user->getTwoFactorBackupCodes() ?? [];
        $hash        = TwoFactorHelper::hashBackupCode($code);

        $restantes = array_values(array_filter(
            $almacenados,
            static fn (string $h): bool => !hash_equals($h, $hash)
        ));

        if (count($restantes) === count($almacenados)) {
            throw new MyCurrentException('INVALID_BACKUP_CODE', 'Invalid or already used backup code.', 422);
        }

        $user->setTwoFactorBackupCodes($restantes);
        $user->setTwoFactorPendingToken(null);
        $user->setTwoFactorPendingTokenExpiresAt(null);
        $this->em->flush();

        return $user;
    }

    /**
     * Último recurso de recuperación: envía un código al correo del usuario para poder
     * reiniciar su 2FA. Arranca desde el pendingToken, es decir, solo después de haber
     * validado usuario y contraseña — de modo que comprometer únicamente el correo no
     * basta para saltarse el segundo factor.
     *
     * @throws MyCurrentException
     */
    public function requestEmailReset(string $pendingToken): void
    {
        $user = $this->resolvePendingToken($pendingToken);

        $code = TwoFactorHelper::generateEmailCode();
        $user->setTwoFactorEmailCode($code);
        $user->setTwoFactorEmailCodeExpiresAt(new DateTimeImmutable('+10 minutes'));
        $this->em->flush();

        $brand = strtolower($user->getCompany()?->getContractWith() ?? 'comremit');
        $this->bus->dispatch(new TwoFactorEmailCodeMessage(
            $user->getEmail(),
            $user->getFirstName(),
            $code,
            $brand
        ));
    }

    /**
     * Valida el código enviado por correo y deja al usuario sin 2FA, manteniendo vivo el
     * pendingToken para que continúe directamente con el enrolamiento del dispositivo
     * nuevo. No emite sesión: se entra al terminar de re-enrolar, nunca antes.
     *
     * @throws MyCurrentException
     */
    public function confirmEmailReset(string $pendingToken, string $code): void
    {
        $user = $this->resolvePendingToken($pendingToken);

        $almacenado = $user->getTwoFactorEmailCode();
        $expira     = $user->getTwoFactorEmailCodeExpiresAt();

        if ($almacenado === null || $expira === null) {
            throw new MyCurrentException('2FA_RESET_NOT_STARTED', 'Reset not started.', 400);
        }
        if (new DateTimeImmutable() > $expira) {
            throw new MyCurrentException('2FA_CODE_EXPIRED', 'Verification code expired. Please start again.', 422);
        }
        if (!hash_equals($almacenado, $code)) {
            throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
        }

        // Se conserva el pendingToken: es lo que autentica el enrolamiento posterior.
        $token   = $user->getTwoFactorPendingToken();
        $expiraT = $user->getTwoFactorPendingTokenExpiresAt();

        $this->disable($user);

        $user->setTwoFactorPendingToken($token);
        $user->setTwoFactorPendingTokenExpiresAt($expiraT);
        $this->em->flush();
    }

    /** Cuántos códigos de respaldo le quedan sin usar. */
    public function countBackupCodes(User $user): int
    {
        return count($user->getTwoFactorBackupCodes() ?? []);
    }

    /**
     * Reset administrativo: deja al usuario sin 2FA para que vuelva a enrolarse en su
     * próximo login. Último recurso cuando ha perdido dispositivo y códigos de respaldo.
     */
    public function resetForUser(User $user): void
    {
        $this->disable($user);
        $user->setTwoFactorBackupCodes(null);
        $this->em->flush();
    }

    /**
     * Desactiva el 2FA. Exige la contraseña actual: sin ella, un token de sesión robado
     * bastaría para apagar el segundo factor, que es precisamente lo que debe proteger
     * la cuenta cuando el token se ve comprometido.
     *
     * @throws MyCurrentException INVALID_CURRENT_PASSWORD
     */
    public function disableWithPassword(User $user, string $password): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new MyCurrentException('INVALID_CURRENT_PASSWORD', 'Current password is incorrect.', 422);
        }

        $this->disable($user);
    }

    public function disable(User $user): void
    {
        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorPendingToken(null);
        $user->setTwoFactorPendingTokenExpiresAt(null);
        $user->setTwoFactorEmailCode(null);
        $user->setTwoFactorEmailCodeExpiresAt(null);
        $user->setTwoFactorLastTimeStep(null);
        // Sin segundo factor activo los códigos de respaldo no tienen sentido y no deben
        // sobrevivir para un enrolamiento futuro.
        $user->setTwoFactorBackupCodes(null);
        $this->em->flush();
    }

    public function requiresTwoFactor(User $user): bool
    {
        if ($user->isTwoFactorEnabled()) {
            return true;
        }

        $mode     = $this->readValue(self::KEY_MODE, 'optional');
        $deadline = $this->readValue(self::KEY_DEADLINE, null);

        if ($mode === 'mandatory' && $deadline !== null) {
            if (new DateTimeImmutable() >= new DateTimeImmutable($deadline)) {
                return true;
            }
        }

        return false;
    }

    /**
     * El usuario debe pasar por el enrolamiento antes de poder verificar: la política
     * global le exige 2FA pero todavía no lo ha activado. Solo tiene sentido consultarlo
     * cuando `requiresTwoFactor()` ya ha devuelto true.
     */
    public function requiresEnrollment(User $user): bool
    {
        return !$user->isTwoFactorEnabled();
    }

    /** @throws MyCurrentException */
    public function startLoginVerification(User $user): array
    {
        $method = $this->readValue(self::KEY_METHOD, 'totp');

        $token = bin2hex(random_bytes(32));
        $user->setTwoFactorPendingToken($token);
        $user->setTwoFactorPendingTokenExpiresAt(new DateTimeImmutable('+10 minutes'));

        if ($method === 'email') {
            $code = TwoFactorHelper::generateEmailCode();
            $user->setTwoFactorEmailCode($code);
            $user->setTwoFactorEmailCodeExpiresAt(new DateTimeImmutable('+10 minutes'));
            $this->em->flush();

            $brand = strtolower($user->getCompany()?->getContractWith() ?? 'comremit');
            $this->bus->dispatch(new TwoFactorEmailCodeMessage(
                $user->getEmail(),
                $user->getFirstName(),
                $code,
                $brand
            ));
        } else {
            $this->em->flush();
        }

        return ['pendingToken' => $token, 'method' => $method];
    }

    /**
     * Resuelve el usuario dueño de un pendingToken vigente. El pendingToken es una
     * credencial de un solo propósito emitida por `/login` tras validar las credenciales,
     * y es lo que autentica tanto la verificación como el enrolamiento durante el login.
     *
     * @throws MyCurrentException INVALID_PENDING_TOKEN | PENDING_TOKEN_EXPIRED
     */
    private function resolvePendingToken(string $pendingToken): User
    {
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy([
            'twoFactorPendingToken' => $pendingToken,
        ]);

        if ($user === null) {
            throw new MyCurrentException('INVALID_PENDING_TOKEN', 'Invalid or expired token.', 401);
        }

        $expiresAt = $user->getTwoFactorPendingTokenExpiresAt();
        if ($expiresAt === null || new DateTimeImmutable() > $expiresAt) {
            throw new MyCurrentException('PENDING_TOKEN_EXPIRED', 'Login session expired. Please log in again.', 401);
        }

        return $user;
    }

    /**
     * Primer paso del enrolamiento durante el login: genera el secreto y la URI del QR
     * para un usuario que aún no tiene 2FA pero al que la política ya se lo exige.
     *
     * @throws MyCurrentException
     */
    public function startEnrollment(string $pendingToken): array
    {
        $user = $this->resolvePendingToken($pendingToken);

        if ($user->isTwoFactorEnabled()) {
            throw new MyCurrentException('2FA_ALREADY_ENABLED', '2FA is already enabled.', 409);
        }

        return $this->startSetup($user);
    }

    /**
     * Segundo paso del enrolamiento durante el login: confirma el primer código, activa el
     * 2FA y consume el pendingToken. Devuelve el usuario para que el controlador emita la
     * sesión, de modo que enrolarse y entrar sean una sola operación, junto con los códigos
     * de respaldo recién generados (única ocasión en que pueden mostrarse en claro).
     *
     * @throws MyCurrentException
     *
     * @return array{user: User, backupCodes: string[]}
     */
    public function confirmEnrollment(string $pendingToken, string $code): array
    {
        $user = $this->resolvePendingToken($pendingToken);

        if ($user->isTwoFactorEnabled()) {
            throw new MyCurrentException('2FA_ALREADY_ENABLED', '2FA is already enabled.', 409);
        }

        $this->confirmSetup($user, $code);

        $user->setTwoFactorPendingToken(null);
        $user->setTwoFactorPendingTokenExpiresAt(null);
        $this->em->flush();

        return ['user' => $user, 'backupCodes' => $this->regenerateBackupCodes($user)];
    }

    /** @throws MyCurrentException */
    public function verifyLoginCode(string $pendingToken, string $code): User
    {
        $user = $this->resolvePendingToken($pendingToken);

        $method = $this->readValue(self::KEY_METHOD, 'totp');

        if ($method === 'totp') {
            $secret = $this->plainSecret($user);
            if ($secret === null) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
            $this->consumeTotpCode($user, $secret, $code);
        } else {
            $storedCode = $user->getTwoFactorEmailCode();
            $codeExpiry = $user->getTwoFactorEmailCodeExpiresAt();
            if ($storedCode === null || $codeExpiry === null) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
            if (new DateTimeImmutable() > $codeExpiry) {
                throw new MyCurrentException('2FA_CODE_EXPIRED', 'Verification code expired. Please log in again.', 422);
            }
            if (!hash_equals($storedCode, $code)) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
        }

        $user->setTwoFactorPendingToken(null);
        $user->setTwoFactorPendingTokenExpiresAt(null);
        $user->setTwoFactorEmailCode(null);
        $user->setTwoFactorEmailCodeExpiresAt(null);
        $this->em->flush();

        return $user;
    }

    /**
     * Valida un código TOTP y lo marca como consumido. Un código solo se acepta una vez:
     * su time step debe ser estrictamente mayor al último usado, de modo que un código
     * interceptado no pueda reutilizarse durante los 30-90 s que dura su ventana.
     *
     * @throws MyCurrentException INVALID_2FA_CODE | 2FA_CODE_ALREADY_USED
     */
    private function consumeTotpCode(User $user, string $secret, string $code): void
    {
        $timeStep = TwoFactorHelper::matchTotpTimeStep($secret, $code);

        if ($timeStep === null) {
            throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
        }

        $lastTimeStep = $user->getTwoFactorLastTimeStep();
        if ($lastTimeStep !== null && $timeStep <= $lastTimeStep) {
            throw new MyCurrentException(
                '2FA_CODE_ALREADY_USED',
                'This code has already been used. Please wait for the next one.',
                422
            );
        }

        $user->setTwoFactorLastTimeStep($timeStep);
    }

    private function readValue(string $key, ?string $default): ?string
    {
        return $this->sysConfigRepo->findCachedValue($key) ?? $default;
    }

    private function writeValue(string $key, string $value): void
    {
        $config = $this->sysConfigRepo->findOneBy(['propertyName' => $key]);
        if ($config === null) {
            $config = (new SysConfig())->setPropertyName($key);
            $this->em->persist($config);
        }
        $config->setPropertyValue($value);
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }

    private function buildIssuer(User $user): string
    {
        $brand = match (strtolower($user->getCompany()?->getContractWith() ?? 'comremit')) {
            'sendmundo' => 'SendMundo',
            default     => 'Comremit',
        };

        $suffix = match ($this->appEnv) {
            'prod'  => '',
            'staging' => ' (staging)',
            default => ' (local)',
        };

        return "Dashboard {$brand}{$suffix}";
    }

    private function dispatchMandatoryNotices(string $deadline): void
    {
        /** @var User[] $users */
        $users = $this->em->getRepository(User::class)->findBy([
            'twoFactorEnabled' => false,
            'isActive'         => true,
        ]);

        foreach ($users as $user) {
            $brand = strtolower($user->getCompany()?->getContractWith() ?? 'comremit');
            $this->bus->dispatch(new TwoFactorMandatoryNotificationMessage(
                $user->getEmail(),
                $user->getFirstName(),
                $deadline,
                $brand
            ));
        }
    }
}
