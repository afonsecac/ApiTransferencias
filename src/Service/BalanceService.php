<?php

namespace App\Service;

use App\DTO\AccountBalanceDto;
use App\DTO\BalanceInDto;
use App\DTO\PaginationResult;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\Client;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Entity\EmailNotification;
use App\Entity\Environment;
use App\Entity\ReportMarked;
use App\Entity\User;
use App\Enums\BalanceOperationEnum;
use App\Enums\BalanceStateEnum;
use App\Exception\MyCurrentException;
use App\Message\BalanceMessage;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BalanceService extends CommonService
{
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
        private readonly MessageBusInterface $messageBus,
        private readonly HttpClientInterface $httpClient,
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
     * @param int $userId
     * @return \App\DTO\AccountBalanceDto
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function balance(int $userId): AccountBalanceDto
    {
        /** @var \App\Repository\BalanceOperationRepository $balanceRepo */
        $balanceRepo = $this->em->getRepository(BalanceOperation::class);
        $balance = $balanceRepo->getBalanceOutput($userId);
        $account = $this->em->getRepository(Account::class)->find($userId);
        if (!is_null($account)) {
            $criticalBalance = $account->getCriticalBalance() ?? $account->getClient()?->getCriticalBalance();
            $minBalance = $account->getMinBalance() ?? $account->getClient()?->getMinBalance();
            if (is_null($criticalBalance)) {
                $criticalBalance = (float)$this->sysConfigRepo->findOneBy([
                    'propertyName' => 'client.critical.balance.operation',
                ])?->getPropertyValue();
            }
            if (is_null($minBalance)) {
                $minBalance = (float)$this->sysConfigRepo->findOneBy([
                    'propertyName' => 'client.min.balance.operation',
                ])?->getPropertyValue();
            }
            /** @var \App\Repository\EmailNotificationRepository $emailNotifRepo */
            $emailNotifRepo = $this->em->getRepository(EmailNotification::class);
            $lastNotification = $emailNotifRepo->getLastNotification($userId);
            if (is_null($lastNotification)) {
                $lastNotification = new EmailNotification();
                $lastNotification->setActive(true);
                $lastNotification->setAccount($account);
                $this->em->persist($lastNotification);
            }
            if ($balance <= $criticalBalance) {
                $tryCritical = $lastNotification->getCriticalTry() ?? 0;
                $lastNotification->setCriticalTry($tryCritical + 1);
                $this->messageBus->dispatch(
                    new BalanceMessage(
                        'CRITICAL',
                        $balance,
                        $account->getContractCurrency() ?? $account->getClient()?->getCurrency() ?? 'USD',
                        $userId
                    )
                );
            } elseif ($balance <= $minBalance) {
                if (is_null($lastNotification->getMinInfo())) {
                    $this->messageBus->dispatch(
                        new BalanceMessage(
                            'RISK',
                            $balance,
                            $account->getContractCurrency() ?? $account->getClient()?->getCurrency() ?? 'USD',
                            $userId
                        )
                    );
                    $lastNotification->setMinInfo(1);
                }
            }
        }

        return new AccountBalanceDto($account?->getContractCurrency() ?? 'USD', $balance);
    }

    /**
     *
     * @param int|null $clientId
     * @return array
     */
    public function getBalancesByEnvironment(?int $clientId): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }
        if (is_null($clientId)) {
            $clientId = $user->getCompany()?->getId();
        }
        if (!$this->security->isGranted('ROLE_ADMIN') && $clientId !== $user->getCompany()?->getId()) {
            throw new AccessDeniedException();
        }

        /** @var \App\Repository\BalanceOperationRepository $balanceRepo */
        $balanceRepo = $this->em->getRepository(BalanceOperation::class);
        $balancesAvailabilities = $balanceRepo->getBalancesInEnvironments(
            $clientId
        );
        $balances = [];
        foreach ($balancesAvailabilities as $balanceItem) {
            $balanceItem['currentBalance'] = round($balanceItem['currentBalance'], 2);
            $lastPaid = $balanceRepo->getLastDateBalance($balanceItem['id']);
            $balanceItem['lastPaid'] = $lastPaid?->getCreatedAt();
            $balanceItem['lastPaidIsAdvance'] = $lastPaid?->isPreviousAmount();
            $balances[] = $balanceItem;
        }

        return $balances;
    }

    /**
     * @param string $env
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getBalancePlatform(string $env): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }
        $environment = $this->em->getRepository(Environment::class)->findOneBy([
            'type' => $env,
            'scope' => 'ET',
            'isActive' => true,
        ]);
        if (is_null($environment)) {
            return [];
        }
        $balanceResponse = $this->httpClient->request(
            'POST',
            $environment->getBasePath().'/information/balance',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $this->serializer->serialize([
                    'environment' => $environment->getType(),
                ], 'json', []),
            ]
        );

        return $balanceResponse->toArray();
    }

    /**
     * @param int $limit
     * @param int|null $companyId
     * @return BalanceOperation[]
     */
    public function recentTransactions(int $limit = 5, int $companyId = null): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        if (is_null($companyId)) {
            $companyId = $user->getCompany()?->getId();
        }
        if (!$this->security->isGranted('ROLE_ADMIN') && $companyId !== $user->getCompany()?->getId()) {
            throw new AccessDeniedException();
        }

        /** @var \App\Repository\BalanceOperationRepository $balanceRepo */
        $balanceRepo = $this->em->getRepository(BalanceOperation::class);
        return $balanceRepo->getRecentTransactions($limit, $companyId);
    }

    /**
     * @param array $filters
     * @param string|null $orderBy
     * @param int $page
     * @param int $limit
     * @return \App\DTO\PaginationResult
     */
    public function getBalanceOperations(
        array $filters = [],
        string $orderBy = null,
        int $page = 0,
        int $limit = 10
    ): PaginationResult {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $companyId = $user->getCompany()?->getId();

        /** @var \App\Repository\BalanceOperationRepository $balanceRepo */
        $balanceRepo = $this->em->getRepository(BalanceOperation::class);
        return $balanceRepo->getAllBalance(
            $filters,
            $orderBy,
            $page,
            $limit,
            $this->security->isGranted('ROLE_ADMIN') ? null : $companyId
        );
    }

    public function createSaleBalance(Account $tenant, CommunicationSaleInfo $sale): void
    {
        // Verificar que no exista ya un balance para esta venta
        $existing = $this->em->getRepository(BalanceOperation::class)->findOneBy([
            'communicationSale' => $sale,
        ]);
        if ($existing !== null) {
            $this->logger->info("Balance already exists for sale {$sale->getId()}, skipping.");
            return;
        }

        $balance = new BalanceOperation();
        $balance->setTenant($tenant);
        $balance->setAmount($sale->getTotalPrice());
        $balance->setCurrency($sale->getCurrency());
        $balance->setState(BalanceStateEnum::COMPLETED->value);
        $balance->setOperationType(BalanceOperationEnum::DEBIT->value);
        $balance->getCalculateTotal();
        $balance->setTotalAmount($balance->getTotalAmount() * -1);
        $balance->setTotalCurrency($sale->getCurrency());
        $balance->setCommunicationSale($sale);

        $this->em->persist($balance);
    }

    /**
     * Reconcilia ventas completadas que no tienen balance asociado.
     * Retorna la cantidad de balances creados.
     */
    public function reconcileMissingBalances(): int
    {
        $salesWithoutBalance = $this->em->createQueryBuilder()
            ->select('s')
            ->from(CommunicationSaleInfo::class, 's')
            ->leftJoin(BalanceOperation::class, 'b', 'WITH', 'b.communicationSale = s')
            ->where('s.state = :completed')
            ->andWhere('b.id IS NULL')
            ->setParameter('completed', CommunicationStateEnum::COMPLETED->value)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($salesWithoutBalance as $sale) {
            $tenant = $sale->getTenant();
            if ($tenant === null) {
                $this->logger->error("Reconcile: sale {$sale->getId()} has no tenant, skipping.");
                continue;
            }
            try {
                $this->createSaleBalance($tenant, $sale);
                $count++;
                $this->logger->info("Reconcile: created balance for sale {$sale->getId()}");
            } catch (\Exception $e) {
                $this->logger->error("Reconcile: failed for sale {$sale->getId()}: " . $e->getMessage());
            }
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /**
     * @throws \App\Exception\MyCurrentException
     */
    public function create(BalanceInDto $balanceInDto): BalanceOperation
    {
        if (!$this->security->isGranted('ROLE_SYSTEM_EDITOR')) {
            throw new AccessDeniedException();
        }
        $balance = new BalanceOperation();
        $balance->setAmount($balanceInDto->getAmount());
        $balance->setCurrency($balanceInDto->getCurrency());
        $balance->setOperationType(BalanceOperationEnum::CREDIT->value);
        $balance->setState(BalanceStateEnum::PENDING->value);
        $balance->setPreviousAmount($balanceInDto->getIsAdvance());
        $account = null;
        if (!is_null($balanceInDto->getAccountId())) {
            $account = $this->em->getRepository(Account::class)->find($balanceInDto->getAccountId());
            if ($account->getEnvironment()?->getId() !== $balanceInDto->getEnvironmentId()) {
                throw new MyCurrentException('BAL002', 'The account doesn\'t exist in the environment');
            }
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                $currentUser = $this->security->getUser();
                if (!$currentUser instanceof User || $account->getClient()?->getId() !== $currentUser->getCompany(
                    )?->getId()) {
                    throw new AccessDeniedException();
                }
            }
        } elseif ($this->security->isGranted('ROLE_ADMIN')) {
            throw new MyCurrentException('BAL003', 'You must select an account');
        }
        if ($balanceInDto->getAmount() < $account->getMinBalance() && $balanceInDto->getAmount() < $account->getClient(
            )?->getMinBalance()) {
            throw new MyCurrentException('BAL004', 'The amount is less than the minimum balance');
        }
        $balance->setTenant($account);
        if (!$this->security->isGranted('ROLE_ADMIN') && !is_null(
                $balanceInDto->getAmountApproved()
            ) && $balanceInDto->getAmountApproved() > 0) {
            throw new AccessDeniedException();
        }
        if (!empty($balanceInDto->getComment())) {
            $balance->setComment($balanceInDto->getComment());
        }
        if (!is_null($balanceInDto->getAmountApproved()) && $balanceInDto->getAmountApproved() > 0) {
            $balance->setState(BalanceStateEnum::COMPLETED->value);
            $balance->setTotalAmount($balanceInDto->getAmountApproved());
            $balance->setTotalCurrency($balanceInDto->getCurrencyApproved());
            $this->closeNotification($account?->getId(), $balance);
        }
        $this->em->persist($balance);
        $this->em->flush();

        return $balance;
    }

    public function balanceImpugned(int $id, BalanceInDto $balanceInDto): ?BalanceOperation
    {
        if (!$this->security->isGranted('ROLE_SYSTEM_EDITOR')) {
            throw new AccessDeniedException();
        }
        $balance = $this->em->getRepository(BalanceOperation::class)->find($id);
        return null;
    }

    public function update(int $id, BalanceInDto $balanceInDto): BalanceOperation
    {
        if (!$this->security->isGranted('ROLE_SYSTEM_EDITOR')) {
            throw new AccessDeniedException();
        }
        $balance = $this->em->getRepository(BalanceOperation::class)->find($id);
        if (is_null($balance)) {
            throw new EntityNotFoundException();
        }
        if ($balanceInDto->getIsRequired()) {
            if ($balance->getState() !== BalanceStateEnum::COMPLETED->value) {
                throw new MyCurrentException('BAL001', 'This operations can\'t required to challenge');
            }
            $balance->setState(BalanceStateEnum::IMPUGNED->value);
        }
        if (!is_null($balanceInDto->getAmountApproved()) && $balanceInDto->getAmountApproved() > 0) {
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedException();
            }
            $balance->setState(BalanceStateEnum::COMPLETED->value);
            $balance->setTotalAmount($balanceInDto->getAmountApproved());
            $balance->setTotalCurrency($balanceInDto->getCurrencyApproved());
            if (!is_null($balance->getTenant())) {
                $account = $balance->getTenant();
                $this->closeNotification($account->getId(), $balance);
            }
            $this->em->flush();
        }

        return $balance;
    }

    public function closeNotification(int $accountId, BalanceOperation $balance): void
    {
        $account = $this->em->getRepository(Account::class)->find($accountId);
        /** @var \App\Repository\EmailNotificationRepository $emailNotifRepo */
        $emailNotifRepo = $this->em->getRepository(EmailNotification::class);
        $lastNotification = $emailNotifRepo->getLastNotification($accountId);
        if (!is_null($lastNotification)) {
            $lastNotification->setBalanceIn($balance);
            $lastNotification->setActive(false);
            $lastNotification->setClosedAt(new \DateTimeImmutable('now'));
        }
        $notification = new EmailNotification();
        $notification->setBalanceIn($balance);
        $notification->setAccount($account);
        $notification->setActive(true);
        $this->em->persist($notification);
    }

    /**
     * @param int $accountId
     * @return array
     */
    public function exportToExcel(int $accountId): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        if (!$this->security->isGranted('ROLE_SYSTEM_EDITOR')) {
            throw new AccessDeniedException();
        }
        $account = $this->em->getRepository(Account::class)->find($accountId);
        $company = $user->getCompany();
        $isMyAccount = false;
        if (!is_null($company)) {
            $companyUser = $this->em->getRepository(Client::class)->find($company->getId());
            if (!is_null($companyUser)) {
                $accounts = $companyUser->getAccounts();
                $isMyAccount = $accounts->contains($account);
            }
        }

        if (!$isMyAccount && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }
        /** @var \App\Repository\BalanceOperationRepository $balanceRepo */
        $balanceRepo = $this->em->getRepository(BalanceOperation::class);
        $lastMarked = $balanceRepo->getLastMarkedAsReported($accountId);
        $previousAmount = $balanceRepo->getPreviousAmount($accountId);
        $arrayToExcelExport = [];
        $opItems = [];
        if (!is_null($lastMarked)) {
            $arrayToExcelExport[] = [
                'id' => $lastMarked->getId(),
                'amount' => round($previousAmount, 2),
                'currency' => $lastMarked->getCurrency(),
                'operation_type' => 'SALDO ANTERIOR',
                'date' => $lastMarked->getCreatedAt()?->format('c'),
                'phone' => '',
                'system_reference' => '',
                'client_reference' => '',
                'legacy_reference' => '',
                'package' => '',
            ];
        }
        $operations = $balanceRepo->filterNoMarkedTransactions($accountId);
        $currentTime = new \DateTimeImmutable('now');
        $lastId = null;
        foreach ($operations as $key => $operation) {
            $operation->setMarkAsReported(true);
            $operation->setReportedDateAt($currentTime);
            $phone = '';
            if ($operation->getOperationType(
                ) === BalanceOperationEnum::DEBIT->value && $operation->getCommunicationSale()) {
                $comSaleRecharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find(
                    $operation->getCommunicationSale()->getId()
                );
                $phone = $comSaleRecharge?->getPhoneNumber();
            }
            $opItems[] = [
                'id' => $operation->getId(),
                'amount' => round($operation->getTotalAmount(), 2),
                'currency' => $operation->getTotalCurrency(),
                'operation_type' => $operation->getOperationType(),
                'date' => $operation->getCreatedAt()?->format('c'),
                'phone' => $phone,
                'system_reference' => $operation->getCommunicationSale()?->getId(),
                'client_reference' => $operation->getCommunicationSale()?->getClientTransactionId(),
                'legacy_reference' => $operation->getCommunicationSale()?->getTransactionId(),
                'package' => $operation->getCommunicationSale()?->getPackage()?->getName(),
            ];
            $operation->setMarkAsReported(true);
            $operation->setReportedDateAt($currentTime);
            $lastId = $operation->getId();
        }
        $arrayToExcelExport = array_merge($arrayToExcelExport, $opItems);
        $name = 'Report_'.$currentTime->format('c').'_'.$account?->getEnvironment()?->getType();
        $arrayResponse = [
            'name' => $name.'.csv',
            'operations' => $arrayToExcelExport,
        ];
        if (!is_null($lastId)) {
            $report = new ReportMarked();
            $report->setAccount($account);
            $report->setClient($account?->getClient());
            $report->setCreatedAt($currentTime);
            $report->setName($name);
            $report->setDataArray($arrayResponse);
            $report->setLastOperationMarked($lastId);
            $this->em->persist($report);
            $this->em->flush();
        }

        return $arrayResponse;
    }
}