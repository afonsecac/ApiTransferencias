<?php

namespace App\Service;

use App\DTO\AccountBalanceDto;
use App\DTO\PaginationResult;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\EmailNotification;
use App\Entity\User;
use App\Message\BalanceMessage;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        EntityManagerInterface               $em,
        Security                             $security,
        ParameterBagInterface                $parameters,
        MailerInterface                      $mailer,
        LoggerInterface                      $logger,
        UserPasswordHasherInterface          $passwordHasher,
        EnvironmentRepository                $environmentRepository,
        SysConfigRepository                  $sysConfigRepo,
        SerializerInterface                  $serializer,
        private readonly MessageBusInterface $messageBus
    )
    {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
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
     * @param int|null $clientId
     * @return array
     */
    public function getBalancesByEnvironment(int $clientId = null): array
    {
        if (is_null($clientId)) {
            return [];
        }
        return $this->em->getRepository(BalanceOperation::class)->getBalancesInEnvironments($clientId);
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
    public function getBalanceOperations(array $filters, string $orderBy = null,  int $page = 0, int $limit = 10): PaginationResult {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $companyId = $user?->getCompany()?->getId();
        return $this->em->getRepository(BalanceOperation::class)->getAllBalance($filters, $orderBy, $page, $limit, $companyId);
    }
}