<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Sender;
use Doctrine\ORM\EntityManagerInterface;

final class SoftDeleteSenderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof Sender) {
            return;
        }

        $data->setRemovedAt(new \DateTimeImmutable('now'));

        $this->em->flush();
    }
}
