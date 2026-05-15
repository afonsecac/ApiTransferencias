<?php

namespace App\DTO\Out;

use App\Entity\Environment;

final class EnvironmentOutDto
{
    public ?int $id = null;
    public ?string $type = null;
    public ?string $basePath = null;
    public ?string $scope = null;
    public ?string $tenantId = null;
    public ?string $providerName = null;
    public ?string $clientId = null;
    public ?float $discount = null;
    public ?string $discountType = null;
    public ?string $opType = null;
    public ?bool $isPreferAdmin = null;
    public ?bool $isActive = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public static function fromEntity(Environment $env): self
    {
        $dto = new self();
        $dto->id = $env->getId();
        $dto->type = $env->getType();
        $dto->basePath = $env->getBasePath();
        $dto->scope = $env->getScope();
        $dto->tenantId = $env->getTenantId();
        $dto->providerName = $env->getProviderName();
        $dto->clientId = $env->getClientId();
        $dto->discount = $env->getDiscount();
        $dto->discountType = $env->getDiscountType();
        $dto->opType = $env->getOpType()?->value;
        $dto->isPreferAdmin = $env->isPreferAdmin();
        $dto->isActive = $env->isActive();
        $dto->createdAt = $env->getCreatedAt()?->format(\DateTimeInterface::ATOM);
        $dto->updatedAt = $env->getUpdatedAt()?->format(\DateTimeInterface::ATOM);
        return $dto;
    }
}
