<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BankCard;
use Doctrine\ORM\EntityManagerInterface;

final class SoftDeleteBankCardProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof BankCard) {
            return;
        }

        $data->setRemovedAt(new \DateTimeImmutable('now'));
        $data->setIsActive(false);

        $this->em->flush();
    }
}
