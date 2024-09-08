<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\CreateOperationDto;
use App\Entity\Account;
use App\Entity\BalanceOperation;
use App\Entity\EmailNotification;
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
            $lastNotification = $this->em->getRepository(EmailNotification::class)->getLastNotification($user->getId());
            if (!is_null($lastNotification)) {
                $lastNotification->setBalanceIn($balance);
                $lastNotification->setClosedAt(new \DateTimeImmutable('now'));
            }
            $notification = new EmailNotification();
            $notification->setBalanceIn($balance);
            $notification->setAccount($user);
            $this->em->persist($notification);

            $this->em->flush();
        }

        return $data;
    }
}
