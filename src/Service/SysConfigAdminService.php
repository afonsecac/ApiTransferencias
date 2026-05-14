<?php

namespace App\Service;

use App\DTO\CreateSysConfigDto;
use App\DTO\UpdateSysConfigDto;
use App\Entity\SysConfig;
use App\Exception\MyCurrentException;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class SysConfigAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
    ) {}

    public function create(CreateSysConfigDto $dto): SysConfig
    {
        $existing = $this->sysConfigRepo->findOneBy(['propertyName' => $dto->getPropertyName()]);
        if ($existing !== null) {
            throw new MyCurrentException(
                'SYS_CONFIG_DUPLICATE',
                "Ya existe una variable con el nombre '{$dto->getPropertyName()}'.",
                Response::HTTP_CONFLICT
            );
        }

        $config = new SysConfig();
        $config->setPropertyName($dto->getPropertyName());
        $config->setPropertyValue($dto->getPropertyValue());
        $config->setIsActive($dto->getIsActive() ?? true);
        $config->setClients($dto->getClients());

        $this->em->persist($config);
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function update(SysConfig $config, UpdateSysConfigDto $dto): SysConfig
    {
        if ($dto->getPropertyName() !== null && $dto->getPropertyName() !== $config->getPropertyName()) {
            $existing = $this->sysConfigRepo->findOneBy(['propertyName' => $dto->getPropertyName()]);
            if ($existing !== null) {
                throw new MyCurrentException(
                    'SYS_CONFIG_DUPLICATE',
                    "Ya existe una variable con el nombre '{$dto->getPropertyName()}'.",
                    Response::HTTP_CONFLICT
                );
            }
            $config->setPropertyName($dto->getPropertyName());
        }

        if ($dto->getPropertyValue() !== null) {
            $config->setPropertyValue($dto->getPropertyValue());
        }

        if ($dto->getIsActive() !== null) {
            $config->setIsActive($dto->getIsActive());
        }

        if ($dto->getClients() !== null) {
            $config->setClients($dto->getClients());
        }

        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function toggle(SysConfig $config): SysConfig
    {
        $config->setIsActive(!$config->isActive());
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function delete(SysConfig $config): void
    {
        $config->setRemovedAt(new \DateTimeImmutable('now'));
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }
}
