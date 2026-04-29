<?php

namespace App\Service;

use App\DTO\UpdateAccountSecurityDto;
use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class AccountSecurityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function update(Account $account, UpdateAccountSecurityDto $dto, bool $isAdmin): Account
    {
        if ($dto->getOrigin() !== null) {
            $account->setOrigin($dto->getOrigin());
        }
        if ($dto->getMinBalance() !== null) {
            $account->setMinBalance($dto->getMinBalance());
        }
        if ($dto->getCriticalBalance() !== null) {
            $account->setCriticalBalance($dto->getCriticalBalance());
        }
        if ($isAdmin) {
            if ($dto->getIsActive() !== null) {
                $account->setIsActive($dto->getIsActive());
                $account->setIsActiveAt(new \DateTimeImmutable('now'));
            }
            if ($dto->getDiscount() !== null) {
                $account->setDiscount($dto->getDiscount());
            }
            if ($dto->getCommission() !== null) {
                $account->setCommission($dto->getCommission());
            }
        }

        $this->em->flush();

        return $account;
    }

    public function regenerateToken(Account $account): Account
    {
        $account->setAccessToken(Uuid::v4());
        $this->em->flush();

        return $account;
    }

    public function toggle(Account $account): Account
    {
        $account->setIsActive(!$account->isActive());
        $account->setIsActiveAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $account;
    }
}
