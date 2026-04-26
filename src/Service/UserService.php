<?php

namespace App\Service;

use App\DTO\AccountPermission;
use App\DTO\ForgotPassword;
use App\DTO\ResetPassword;
use App\Entity\Account;
use App\Entity\Client;
use App\EntityPaginator\PaginatorResponse;
use App\Entity\Environment;
use App\Entity\NavigationItem;
use App\Entity\User;
use App\Entity\UserCode;
use App\Entity\UserPassword;
use App\Entity\UserSession;
use App\Message\ForgotPasswordMessage;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Util\DashboardUtil;
use Doctrine\ORM\EntityManagerInterface;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS512;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;
use MiladRahimi\Jwt\Parser;
use MiladRahimi\Jwt\Validator\DefaultValidator;
use MiladRahimi\Jwt\Validator\Rules\IdenticalTo;
use MiladRahimi\Jwt\Validator\Rules\NewerThan;
use MiladRahimi\Jwt\Validator\Rules\OlderThanOrSame;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UserService extends CommonService
{
    private string $issValue;
    private string $audValue;

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
        protected readonly RoleHierarchyInterface $roleHierarchy,
        protected readonly MessageBusInterface $messageBus
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
        $this->issValue = $parameters->get('app.jwt.issuer');
        $this->audValue = $parameters->get('app.jwt.audience');
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function update(User $user): User
    {
        $userAuth = $this->security->getUser();
        if (!$userAuth instanceof User || $userAuth->getId() !== $user->getId()) {
            throw new AccessDeniedException();
        }
        $currentUser = $this->em->getRepository(User::class)->find($userAuth->getId());
        $currentUser?->setLangPreference($userAuth->getLangPreference());
        $this->em->flush();

        return $user;
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
        /** @var \App\Repository\UserSessionRepository $sessionRepo */
        $sessionRepo = $this->em->getRepository(UserSession::class);
        return $sessionRepo->getActiveUserSession($userId);
    }

    public function parserJwt(?DefaultValidator $validator = null): Parser
    {
        return new Parser($this->createSignature(), $validator);
    }

    public function createPayloadUser(User $user): array
    {
        $roles = $user->getRoles();
        $userRoles = [];
        $accessIds = [];
        try {
            $userRoles = $this->roleHierarchy->getReachableRoleNames($roles);
            $clientId = $user->getCompany()?->getId();
            $userId = $user->getId();
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                $clientId = null;
                $userId = null;
            }
            /** @var \App\Repository\NavigationItemRepository $navRepo */
            $navRepo = $this->em->getRepository(NavigationItem::class);
            $allIds = $navRepo->accessIds($userRoles, $clientId, $userId);
            foreach ($allIds as $itemIds) {
                if (!in_array($itemIds['parentId'], $accessIds, true)) {
                    $accessIds[] = $itemIds['parentId'];
                }
                if (!in_array($itemIds['childId'], $accessIds, true)) {
                    $accessIds[] = $itemIds['childId'];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $userRoles,
            'isActive' => $user->isActive(),
            'isCheckValidation' => $user->isCheckValidation(),
            'jobTitle' => $user->getJobTitle(),
            'middleName' => $user->getMiddleName(),
            'removedAt' => $user->getRemovedAt(),
            'company' => $user->getCompany(),
            'permission' => $user->getPermission(),
            'currentIp' => $user->getCurrentIp(),
            'status' => 'online',
            'langPreference' => $user->getLangPreference() ?? 'en',
            'name' => $user->getFirstName().' '.$user->getLastName(),
            'accessIds' => $accessIds,
        ];
    }

    public function createUserFromPayload(string $payload): User
    {
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
        $payloadUser = $this->createPayloadUser($user);
        return $this->createTokenFromPayload($user, $payloadUser);
    }

    public function createTokenFromPayload(User $user, array $payloadUser): string
    {
        $generator = $this->generatorJwt();
        $timeToExpire = $this->parameters->get('app.jwt.expired');
        $currentTime = new \DateTimeImmutable('now');
        $payload = $this->serializer->serialize($payloadUser, 'json', [
            'groups' => ['profile'],
        ]);

        return $generator->generate([
            'data' => $payload,
            'iat' => $currentTime->getTimestamp(),
            'sub' => $user->getId(),
            'exp' => (new \DateTimeImmutable('now'))->modify('+'.$timeToExpire.' minutes')->getTimestamp(),
            'nbf' => $currentTime->getTimestamp(),
            'iss' => $this->issValue,
            'aud' => $this->audValue,
        ]);
    }

    /**
     * @param \App\DTO\ForgotPassword $forgotPassword
     * @return array|true[]
     * @throws \DateMalformedStringException
     * @throws \Random\RandomException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function forgotPassword(ForgotPassword $forgotPassword): array
    {
        // Siempre devolver la misma respuesta para evitar enumeración de emails
        $successResponse = ['send' => true];

        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $forgotPassword->getEmail(),
            'isActive' => true,
        ]);
        if (is_null($user)) {
            $this->logger->info('Forgot password attempt for non-existent email: ' . $forgotPassword->getEmail());
            return $successResponse;
        }

        /** @var \App\Repository\UserCodeRepository $userCodeRepo */
        $userCodeRepo = $this->em->getRepository(UserCode::class);
        $userCode = $userCodeRepo->getLastCodeByEmail($forgotPassword->getEmail());
        if (is_null($userCode)) {
            $userCode = new UserCode();
            $userCode->setCode(DashboardUtil::generateUniqueCode());
            $userCode->setUserInfo($user);
            $userCode->setInvalidAt((new \DateTimeImmutable('now'))->modify('+1 hour'));
            $this->em->persist($userCode);
            $this->em->flush();
        }

        $this->messageBus->dispatch(
            new ForgotPasswordMessage(
                $user->getEmail(),
                $userCode->getCode(),
                $user->getCompany()?->getContractWith(),
                $user->getFirstName()
            )
        );

        return $successResponse;
    }

    /**
     * @param \App\DTO\ResetPassword $resetPassword
     * @return array<string, mixed>
     */
    public function resetPassword(ResetPassword $resetPassword): array
    {
        if ($resetPassword->getPassword() !== $resetPassword->getPasswordConfirm()) {
            return [
                'changed' => false,
                'error' => [
                    'message' => 'The password don\'t match',
                ],
                'status' => Response::HTTP_BAD_REQUEST,
            ];
        }
        /** @var \App\Repository\UserCodeRepository $userCodeRepo */
        $userCodeRepo = $this->em->getRepository(UserCode::class);
        $userCode = $userCodeRepo->getByCodeAndEmailNotUsed(
            $resetPassword->getCode(),
            $resetPassword->getEmail()
        );
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $resetPassword->getEmail(),
            'isActive' => true,
        ]);
        if (is_null($userCode) || is_null($user)) {
            return [
                'changed' => false,
                'error' => [
                    'message' => 'User information not found',
                ],
                'status' => Response::HTTP_NOT_FOUND,
            ];
        }
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $resetPassword->getEmail(),
        ]);
        $codifiedPassword = $this->passwordHasher->hashPassword($user, $resetPassword->getPassword());
        $userPassword = new UserPassword();
        $userPassword->setUserHistoric($user);
        $userPassword->setHistoricPassword($user->getPassword());
        $this->em->persist($userPassword);
        $user->setPassword($codifiedPassword);
        $user->setCheckValidation(true);
        $user->setIsActive(true);
        $userCode->setEmailValidated(true);
        $userCode->setUsedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return [
            'changed' => true,
        ];
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
            $validator->addRequiredRule('nbf', new OlderThanOrSame($currentTime->getTimestamp()));
            $validator->addRequiredRule('iat', new OlderThanOrSame($currentTime->getTimestamp()));
            $validator->addRequiredRule('iss', new IdenticalTo($this->issValue));
            $validator->addRequiredRule('aud', new IdenticalTo($this->audValue));
            $parser = $this->parserJwt($validator);
            $objectParser = (object)$parser->parse($token);
            if (property_exists($objectParser, 'jti') && !is_null($objectParser->jti)) {
                $sessionId = $objectParser->jti;
                $session = $this->em->getRepository(UserSession::class)->find($sessionId);
                if (!is_null($session)) {
                    $this->updateActiveSession($session);
                }
            }

            return $this->createUserFromPayload($objectParser->data);
        } catch (\Exception $e) {
            $tokenSplit = explode('.', $token);
            $dataCode = base64_decode($tokenSplit[1]);
            $objectDecode = json_decode($dataCode, true);
            if (is_array($objectDecode) && array_key_exists('jti', $objectDecode) && is_numeric($objectDecode['jti'])) {
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
        /** @var \App\Repository\UserSessionRepository $sessionRepo */
        $sessionRepo = $this->em->getRepository(UserSession::class);
        $sessions = $sessionRepo->sessionUnclosed((int)$timeToExpire);
        $this->closeAllSessions($sessions);
    }

    public function closeSessionOfUser(int $userId): int
    {
        if (!$this->security->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw new AccessDeniedException();
        }
        /** @var \App\Repository\UserSessionRepository $sessionRepo */
        $sessionRepo = $this->em->getRepository(UserSession::class);
        $sessions = $sessionRepo->sessionUnclosedByUser($userId);
        $this->closeAllSessions($sessions);

        return count($sessions);
    }

    public function closeMySession(int $userId): bool
    {
        /** @var \App\Repository\UserSessionRepository $sessionRepo */
        $sessionRepo = $this->em->getRepository(UserSession::class);
        $sessions = $sessionRepo->sessionUnclosedByUser($userId);
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

    public function allUsers(int $page = 0, int $limit = 20, array $filters = [], array $orders = []): PaginatorResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $companyId = $user->getCompany()?->getId();
        $active = !is_null($companyId) ? true : null;

        /** @var \App\Repository\UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        return $userRepo->searchAllUsersInCompany($companyId, $active, $page, $limit);
    }

    public function getPermissionUsed(): AccountPermission
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $clients = $this->em->getRepository(Client::class)->findBy([
                'isActive' => true,
            ], [
                'companyName' => 'ASC',
            ]);
            /** @var \App\Repository\AccountRepository $accountRepo */
            $accountRepo = $this->em->getRepository(Account::class);
            $accounts = $accountRepo->getAccounts();
            $environments = $this->em->getRepository(Environment::class)->findBy([], [
                'opType' => 'ASC',
                'providerName' => 'ASC',
                'isPreferAdmin' => 'ASC',
            ]);
            $preferEnvironment = null;
            foreach ($environments as $environment) {
                if ($environment->isPreferAdmin()) {
                    $preferEnvironment = $environment;
                    break;
                }
            }

            return new AccountPermission($accounts, $environments, $clients, $preferEnvironment, null);
        }
        if ($this->security->isGranted('ROLE_SYSTEM_USER')) {
            $client = $this->em->getRepository(Client::class)->find($user->getCompany()?->getId());
            $accounts = $this->em->getRepository(Account::class)->findBy([
                'client' => $client,
                'isActive' => true,
            ]);
            $preferAccount = null;
            $preferEnvironment = null;
            $environments = [];
            foreach ($accounts as $account) {
                if ($account->isPreferAdmin()) {
                    $preferAccount = $account;
                    $preferEnvironment = $account->getEnvironment();
                }
                $environments[] = $account->getEnvironment();
            }

            return new AccountPermission($accounts, $environments, [$client], $preferEnvironment, $preferAccount);
        }

        throw new AccessDeniedException('Insufficient permissions to access account information.');
    }
}