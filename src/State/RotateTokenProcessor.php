<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\RotateToken;
use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

final class RotateTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RotateToken
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            throw new AccessDeniedHttpException();
        }

        $newUuid = Uuid::v7();
        $user->setAccessToken($newUuid);
        $this->em->flush();

        $result = new RotateToken();
        $result->setToken(base64_encode((string) $newUuid));

        return $result;
    }
}
