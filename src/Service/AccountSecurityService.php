<?php

namespace App\Service;

use App\DTO\UpdateAccountSecurityDto;
use App\Entity\Account;
use App\Entity\User;
use App\Security\MarketAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class AccountSecurityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MarketAccess $marketAccess,
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
            if ($dto->getContractCurrency() !== null) {
                $account->setContractCurrency($dto->getContractCurrency());
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

    /**
     * Abre o cierra el mercado de una cuenta (ADR-001) y registra quién lo cambió y cuándo. Si el valor ya era el
     * pedido no se toca nada, tampoco la auditoría: el registro es del último cambio real.
     */
    public function setMarketEnabled(Account $account, bool $enabled, ?User $by): Account
    {
        if ($account->isMarketEnabled() === $enabled) {
            return $account;
        }

        $account->setMarketEnabled($enabled);
        $account->markMarketChanged($by, new \DateTimeImmutable('now'));
        $this->em->flush();

        return $account;
    }

    /**
     * ¿Está el mercado realmente abierto para la cuenta? Requiere su flag y el interruptor global.
     */
    public function isMarketOpen(Account $account): bool
    {
        return $this->marketAccess->isOpenFor($account);
    }

    public function toggle(Account $account): Account
    {
        $account->setIsActive(!$account->isActive());
        $account->setIsActiveAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $account;
    }
}
