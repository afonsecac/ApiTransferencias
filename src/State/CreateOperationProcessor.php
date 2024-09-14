<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\CreateOperationDto;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CreateOperationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @inheritDoc
     * @return CreateOperationDto
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreateOperationDto
    {
        $user = $this->security->getUser();
        if ($data instanceof CreateOperationDto && $user instanceof Account) {
            $balance = new BalanceOperation();
            $balance->setAmount($data->amount);
            $balance->setCurrency($data->currency);
            $balance->setTenant($user);
            $balance->setState('PENDING');
            $balance->setOperationType('CREDIT');

            $this->em->persist($balance);
            $this->em->flush();
        }

        return $data;
    }
}
