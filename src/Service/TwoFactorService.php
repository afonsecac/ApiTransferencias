<?php

namespace App\Service;

use App\DTO\TwoFactorConfigDto;
use App\Entity\SysConfig;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Message\TwoFactorEmailCodeMessage;
use App\Message\TwoFactorMandatoryNotificationMessage;
use App\Repository\SysConfigRepository;
use App\Security\TwoFactorHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TwoFactorService
{
    private const KEY_MODE     = '2fa.mode';
    private const KEY_METHOD   = '2fa.method';
    private const KEY_DEADLINE = '2fa.deadline';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository    $sysConfigRepo,
        private readonly MessageBusInterface    $bus,
        private readonly string                 $appEnv,
    ) {}

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
            $user->setTwoFactorSecret($secret);
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
            $secret = $user->getTwoFactorSecret();
            if ($secret === null) {
                throw new MyCurrentException('2FA_SETUP_NOT_STARTED', '2FA setup not started.', 400);
            }
            if (!TwoFactorHelper::verifyTotp($secret, $code)) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
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

    public function disable(User $user): void
    {
        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorPendingToken(null);
        $user->setTwoFactorPendingTokenExpiresAt(null);
        $user->setTwoFactorEmailCode(null);
        $user->setTwoFactorEmailCodeExpiresAt(null);
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

    /** @throws MyCurrentException */
    public function verifyLoginCode(string $pendingToken, string $code): User
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

        $method = $this->readValue(self::KEY_METHOD, 'totp');

        if ($method === 'totp') {
            $secret = $user->getTwoFactorSecret();
            if ($secret === null || !TwoFactorHelper::verifyTotp($secret, $code)) {
                throw new MyCurrentException('INVALID_2FA_CODE', 'Invalid verification code.', 422);
            }
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

    private function readValue(string $key, ?string $default): ?string
    {
        return $this->sysConfigRepo->findOneBy(['propertyName' => $key])?->getPropertyValue() ?? $default;
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
