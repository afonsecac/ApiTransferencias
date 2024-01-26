<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Repository\BalanceOperationRepository;
use Symfony\Bundle\SecurityBundle\Security;

class BalanceProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly BalanceOperationRepository $operationRepository,
    )
    {
    }
    /**
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!($operation instanceof CollectionOperationInterface) && $user instanceof Account) {
            $amount = $this->operationRepository->getBalanceOutput($user->getId());
            return new AccountBalanceDto( 'USD', $amount);
        }
        return new AccountBalanceDto('USD', 0);
    }
}
