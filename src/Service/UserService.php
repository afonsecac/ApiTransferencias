<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS512;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;
use MiladRahimi\Jwt\Parser;
use MiladRahimi\Jwt\Validator\DefaultValidator;
use MiladRahimi\Jwt\Validator\Rules\GreaterThan;
use MiladRahimi\Jwt\Validator\Rules\IdenticalTo;
use MiladRahimi\Jwt\Validator\Rules\LessThan;
use MiladRahimi\Jwt\Validator\Rules\NewerThan;
use MiladRahimi\Jwt\Validator\Rules\OlderThan;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class UserService extends CommonService
{
    static string $ISS_VALUE = 'https://api-tx.sendmundo.com';
    static string $AUD_VALUE = 'https://dashboard.sendmundo.com';
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        SerializerInterface $serializer,
    ) {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer
        );
    }

    /**
     * @return \MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS512
     */
    public function createSignature(): HS512
    {
        $keyIndex = $this->parameters->get('app.secret');
        return new HS512(new HmacKey($keyIndex));
    }

    public function generatorJwt(): Generator
    {
        return new Generator(
            $this->createSignature()
        );
    }

    /**
     * @throws \Exception
     */
    public function createUser(User $user): User
    {
        try {
            $password = $user->getPassword();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $this->em->persist($user);
            $this->em->flush();

            return $user;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    public function getActiveSession(int $userId): ?UserSession
    {
        return $this->em->getRepository(UserSession::class)->getActiveUserSession($userId);
    }

    public function parserJwt(DefaultValidator $validator = null): Parser
    {
        return new Parser($this->createSignature(), $validator);
    }

    public function createPayloadUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'isCheckValidation' => $user->isCheckValidation(),
            'jobTitle' => $user->getJobTitle(),
            'middleName' => $user->getMiddleName(),
            'removedAt' => $user->getRemovedAt(),
            'company' => $user->getCompany(),
            'permission' => $user->getPermission(),
            'currentIp' => $user->getCurrentIp(),
        ];
    }

    public function createUserFromPayload(string $payload): User
    {
        $user = new User();
        return $this->serializer->deserialize($payload, User::class, 'json');
    }

    /**
     * @param \App\Entity\User $user
     * @param string $ip
     * @return string
     * @throws \MiladRahimi\Jwt\Exceptions\JsonEncodingException
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     */
    public function createSession(User $user, string $ip): string
    {
        $userSession = new UserSession();
        $userSession->setUserBySession($user);
        $userSession->setOriginIp($ip);
        $userSession->setLastAccessAt(new \DateTimeImmutable('now'));
        $this->em->persist($userSession);
        $token = $this->createToken($user, $userSession);
        $args = [
            'ip' => $ip,
            'token' => $token,
        ];
        $userSession->setAnotherInfo($args);
        $this->em->flush();

        return $token;
    }

    /**
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonEncodingException
     */
    public function createToken(User $user, ?UserSession $userSession): string
    {
        $generator = $this->generatorJwt();
        $timeToExpire = $this->parameters->get('app.jwt.expired');
        $currentTime = new \DateTimeImmutable('now');
        $payloadUser = $this->createPayloadUser($user);
        $payload = $this->serializer->serialize($payloadUser, 'json');
        return $generator->generate([
            'data' => $payload,
            'iat' => $currentTime->getTimestamp(),
            'sub' => $user->getId(),
            'exp' => (new \DateTimeImmutable('now'))->modify('+' . $timeToExpire . ' minutes')->getTimestamp(),
            'nbf' => $currentTime->getTimestamp(),
            'iss' => self::$ISS_VALUE,
            'aud' => self::$AUD_VALUE,
            'jti' => $userSession?->getId(),
        ]);
    }

    /**
     * @param string $token
     * @return \App\Entity\User
     * @throws \DateMalformedStringException
     * @throws \MiladRahimi\Jwt\Exceptions\InvalidSignatureException
     * @throws \MiladRahimi\Jwt\Exceptions\InvalidTokenException
     * @throws \MiladRahimi\Jwt\Exceptions\JsonDecodingException
     * @throws \MiladRahimi\Jwt\Exceptions\SigningException
     * @throws \MiladRahimi\Jwt\Exceptions\ValidationException
     */
    public function parser(string $token): User
    {
        $validator = new DefaultValidator();
        try {
            $currentTime = new \DateTimeImmutable('now');

            $validator->addRequiredRule('exp', new NewerThan($currentTime->getTimestamp()));
            $validator->addRequiredRule('nbf', new OlderThan($currentTime->getTimestamp()));
            $validator->addRequiredRule('iat', new OlderThan($currentTime->getTimestamp()));
            $validator->addRequiredRule('iss', new IdenticalTo(self::$ISS_VALUE));
            $validator->addRequiredRule('aud', new IdenticalTo(self::$AUD_VALUE));
            $parser = $this->parserJwt($validator);
            $objectParser = (object)$parser->parse($token);
            $sessionId = $objectParser->jti;
            $session = $this->em->getRepository(UserSession::class)->find($sessionId);
            if (!is_null($session)) {
                $this->updateActiveSession($session);
            }

            return $this->createUserFromPayload($objectParser->data);
        } catch (\Exception $e) {
            $tokenSplit = explode('.', $token);
            $dataCode = base64_decode($tokenSplit[1]);
            $objectDecode = $this->serializer->decode($dataCode, 'json');
            if (isset($objectDecode['jti']) && is_numeric($objectDecode['jti'])) {
                $session = $this->em->getRepository(UserSession::class)->find($objectDecode['jti']);
                if (!is_null($session)) {
                    $this->closeAllSessions([$session]);
                }
            }
            throw $e;
        }
    }

    public function updateActiveSession(UserSession $userSession): void
    {
        $userSession->setLastAccessAt(new \DateTimeImmutable('now'));
        $this->em->flush();
    }

    public function closeAllOpenSessions(): void
    {
        $timeToExpire = $this->parameters->get('app.jwt.expired');
        $sessions = $this->em->getRepository(UserSession::class)->sessionUnclosed((int) $timeToExpire);
        $this->closeAllSessions($sessions);
    }

    public function closeSessionOfUser(int $userId): int
    {
        if (!$this->security->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw new AccessDeniedException();
        }
        $sessions = $this->em->getRepository(UserSession::class)->sessionUnclosedByUser($userId);
        $this->closeAllSessions($sessions);
        return count($sessions);
    }

    public function closeMySession(int $userId): bool
    {
        $sessions = $this->em->getRepository(UserSession::class)->sessionUnclosedByUser($userId);
        $this->closeAllSessions($sessions);
        return count($sessions) !== 0;
    }

    public function closeAllSessions(array $sessions): void
    {
        $sessionIsClosed = false;
        foreach ($sessions as $session) {
            if ($session instanceof UserSession) {
                $session->setClosedAt(new \DateTimeImmutable('now'));
                $sessionIsClosed = true;
            }
        }
        if ($sessionIsClosed) {
            $this->em->flush();
        }
    }

    public function allUsers(int $page = 0, int $limit = 20, array $filters = [], array $orders = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $companyId = $user->getCompany()?->getId();
        $active = !is_null($companyId) ? true : null;
        return $this->em->getRepository(User::class)->searchAllUsersInCompany($companyId, $active, $page, $limit);
    }
}