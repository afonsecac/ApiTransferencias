<?php

namespace App\Service;

use App\DTO\CreateEnvironmentDto;
use App\DTO\UpdateEnvironmentDto;
use App\Entity\Environment;
use App\Enums\PlatformTypeEnum;
use App\Exception\MyCurrentException;
use App\Repository\EnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;

class EnvironmentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EnvironmentRepository $environmentRepository,
    ) {}

    public function findOrFail(int $id): Environment
    {
        $env = $this->environmentRepository->find($id);
        if ($env === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }
        return $env;
    }

    public function create(CreateEnvironmentDto $dto): Environment
    {
        $type = $dto->getType();
        $basePath = $dto->getBasePath();
        $providerName = $dto->getProviderName();
        $clientId = $dto->getClientId();
        $clientSecret = $dto->getClientSecret();

        if ($type === null || $basePath === null || $providerName === null || $clientId === null || $clientSecret === null) {
            throw new MyCurrentException('INVALID_DATA', 'Required fields are missing', 400);
        }

        $this->assertUnique($type, $providerName);

        $env = new Environment();
        $env->setType($type);
        $env->setBasePath($basePath);
        $env->setProviderName($providerName);
        $env->setClientId($clientId);
        $env->setClientSecret($clientSecret);
        $env->setScope($dto->getScope());
        $env->setTenantId($dto->getTenantId());
        $env->setDiscount($dto->getDiscount() ?? 0);
        $env->setDiscountType($dto->getDiscountType() ?? '%');
        $env->setOpType($dto->getOpType() !== null ? PlatformTypeEnum::from($dto->getOpType()) : null);
        $env->setPreferAdmin($dto->getIsPreferAdmin());
        $env->setIsActive($dto->getIsActive() ?? true);

        $this->em->persist($env);
        $this->em->flush();

        return $env;
    }

    public function update(Environment $env, UpdateEnvironmentDto $dto): Environment
    {
        $newType = $dto->getType() ?? $env->getType();
        $newProviderName = $dto->getProviderName() ?? $env->getProviderName();

        if ($newType === null || $newProviderName === null) {
            throw new MyCurrentException('INVALID_DATA', 'type and providerName are required', 400);
        }

        if ($newType !== $env->getType() || $newProviderName !== $env->getProviderName()) {
            $this->assertUnique($newType, $newProviderName, $env->getId());
        }

        if ($dto->getType() !== null) {
            $env->setType($dto->getType());
        }
        if ($dto->getBasePath() !== null) {
            $env->setBasePath($dto->getBasePath());
        }
        if ($dto->getProviderName() !== null) {
            $env->setProviderName($dto->getProviderName());
        }
        if ($dto->getClientId() !== null) {
            $env->setClientId($dto->getClientId());
        }
        if ($dto->getClientSecret() !== null) {
            $env->setClientSecret($dto->getClientSecret());
        }
        if ($dto->getScope() !== null) {
            $env->setScope($dto->getScope());
        }
        if ($dto->getTenantId() !== null) {
            $env->setTenantId($dto->getTenantId());
        }
        if ($dto->getDiscount() !== null) {
            $env->setDiscount($dto->getDiscount());
        }
        if ($dto->getDiscountType() !== null) {
            $env->setDiscountType($dto->getDiscountType());
        }
        if ($dto->getOpType() !== null) {
            $env->setOpType(PlatformTypeEnum::from($dto->getOpType()));
        }
        if ($dto->getIsPreferAdmin() !== null) {
            $env->setPreferAdmin($dto->getIsPreferAdmin());
        }
        if ($dto->getIsActive() !== null) {
            $env->setIsActive($dto->getIsActive());
        }

        $this->em->flush();

        return $env;
    }

    private function assertUnique(string $type, string $providerName, ?int $excludeId = null): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Environment::class, 'e')
            ->where('e.type = :type AND e.providerName = :providerName')
            ->setParameter('type', $type)
            ->setParameter('providerName', $providerName);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        if ((int) $qb->getQuery()->getSingleScalarResult() > 0) {
            throw new MyCurrentException(
                'ENVIRONMENT_DUPLICATE',
                sprintf('An environment with type "%s" and provider "%s" already exists', $type, $providerName),
                409
            );
        }
    }
}
