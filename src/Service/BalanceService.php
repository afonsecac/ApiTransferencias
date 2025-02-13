<?php

namespace App\Service;

use App\DTO\AccountBalanceDto;
use App\DTO\BalanceInDto;
use App\DTO\PaginationResult;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\Client;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\EmailNotification;
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
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

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
        private readonly MessageBusInterface $messageBus
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
        $balance = $this->em->getRepository(BalanceOperation::class)->getBalanceOutput($userId);
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
            $lastNotification = $this->em->getRepository(EmailNotification::class)->getLastNotification($userId);
            if (is_null($lastNotification)) {
                $lastNotification = new EmailNotification();
                $lastNotification->setActive(true);
                $lastNotification->setAccount($account);
                $this->em->persist($lastNotification);
            }
            if ($balance <= $criticalBalance) {
                $tryCritical = $lastNotification?->getCriticalTry() ?? 0;
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
                if (is_null($lastNotification) || is_null($lastNotification->getMinInfo())) {
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
    public function getBalancesByEnvironment(int $clientId = null): array
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

        $balancesAvailabilities = $this->em->getRepository(BalanceOperation::class)->getBalancesInEnvironments(
            $clientId
        );
        $balances = [];
        foreach ($balancesAvailabilities as $balanceItem) {
            $balanceItem['currentBalance'] = round($balanceItem['currentBalance'], 2);
            $lastPaid = $this->em->getRepository(BalanceOperation::class)->getLastDateBalance($balanceItem['id']);
            $balanceItem['lastPaid'] = $lastPaid?->getCreatedAt();
            $balanceItem['lastPaidIsAdvance'] = $lastPaid?->isPreviousAmount();
            $balances[] = $balanceItem;
        }

        return $balances;
    }

    /**
     * @param int $limit
     * @return BalanceOperation[]
     */
    public function recentTransactions(int $limit = 5): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $companyId = $user->getCompany()?->getId();

        return $this->em->getRepository(BalanceOperation::class)->getRecentTransactions($limit, $companyId);
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
        $companyId = $user?->getCompany()?->getId();

        return $this->em->getRepository(BalanceOperation::class)->getAllBalance(
            $filters,
            $orderBy,
            $page,
            $limit,
            $this->security->isGranted('ROLE_ADMIN') ? null : $companyId
        );
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
            if ($this->security->isGranted('ROLE_SYSTEM_EDITOR') && !$this->security->isGranted('ROLE_ADMIN')) {
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
            $this->em->flush();
        }

        return $balance;
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
        $lastMarked = $this->em->getRepository(BalanceOperation::class)->getLastMarkedAsReported($accountId);
        $previousAmount = $this->em->getRepository(BalanceOperation::class)->getPreviousAmount($accountId);
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
        $operations = $this->em->getRepository(BalanceOperation::class)->filterNoMarkedTransactions($accountId);
        $currentTime = new \DateTimeImmutable('now');
        $lastId = null;
        foreach ($operations as $key => $operation) {
            $operation?->setMarkAsReported(true);
            $operation?->setReportedDateAt($currentTime);
            $phone = '';
            if ($operation->getOperationType(
                ) === BalanceOperationEnum::DEBIT->value && $operation->getCommunicationSale()) {
                $comSaleRecharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find(
                    $operation->getCommunicationSale()->getId()
                );
                $phone = $comSaleRecharge?->getPhoneNumber();
            }
            $opItems[] = [
                'id' => $operation?->getId(),
                'amount' => round($operation?->getTotalAmount(), 2),
                'currency' => $operation?->getTotalCurrency(),
                'operation_type' => $operation?->getOperationType(),
                'date' => $operation?->getCreatedAt()?->format('c'),
                'phone' => $phone,
                'system_reference' => $operation?->getCommunicationSale()?->getId(),
                'client_reference' => $operation?->getCommunicationSale()?->getClientTransactionId(),
                'legacy_reference' => $operation?->getCommunicationSale()?->getTransactionId(),
                'package' => $operation?->getCommunicationSale()?->getPackage()?->getName(),
            ];
            $operation->setMarkAsReported(true);
            $operation->setReportedDateAt($currentTime);
            $lastId = $operation?->getId();
        }
        $arrayToExcelExport = array_merge($arrayToExcelExport, $opItems);
        $name = 'Report_'.$currentTime->format('c').'_'.$account?->getEnvironment()?->getType();
        $arrayResponse = [
            'name' => $name.'.xlsx',
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