<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Beneficiary;
use Doctrine\ORM\EntityManagerInterface;

final class SoftDeleteBeneficiaryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof Beneficiary) {
            return;
        }

        $data->setRemoveAt(new \DateTimeImmutable('now'));
        $data->setIsActive(false);

        $this->em->flush();
    }
}
