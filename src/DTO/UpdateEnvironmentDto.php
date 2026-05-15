<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateEnvironmentDto implements IInput
{
    #[Assert\Length(max: 10)]
    protected ?string $type = null;

    #[Assert\Length(max: 255)]
    protected ?string $basePath = null;

    #[Assert\Length(max: 255)]
    protected ?string $providerName = null;

    #[Assert\Length(max: 255)]
    protected ?string $clientId = null;

    #[Assert\Length(max: 255)]
    protected ?string $clientSecret = null;

    #[Assert\Length(max: 255)]
    protected ?string $scope = null;

    #[Assert\Length(max: 255)]
    protected ?string $tenantId = null;

    #[Assert\PositiveOrZero]
    protected ?float $discount = null;

    #[Assert\Length(max: 3)]
    protected ?string $discountType = null;

    #[Assert\Choice(choices: ['COM', 'REM'])]
    protected ?string $opType = null;

    protected ?bool $isPreferAdmin = null;

    protected ?bool $isActive = null;

    public function __construct(
        ?string $type = null,
        ?string $basePath = null,
        ?string $providerName = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $scope = null,
        ?string $tenantId = null,
        ?float $discount = null,
        ?string $discountType = null,
        ?string $opType = null,
        ?bool $isPreferAdmin = null,
        ?bool $isActive = null,
    ) {
        $this->type = $type;
        $this->basePath = $basePath;
        $this->providerName = $providerName;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->scope = $scope;
        $this->tenantId = $tenantId;
        $this->discount = $discount;
        $this->discountType = $discountType;
        $this->opType = $opType;
        $this->isPreferAdmin = $isPreferAdmin;
        $this->isActive = $isActive;
    }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $v): void { $this->type = $v; }

    public function getBasePath(): ?string { return $this->basePath; }
    public function setBasePath(?string $v): void { $this->basePath = $v; }

    public function getProviderName(): ?string { return $this->providerName; }
    public function setProviderName(?string $v): void { $this->providerName = $v; }

    public function getClientId(): ?string { return $this->clientId; }
    public function setClientId(?string $v): void { $this->clientId = $v; }

    public function getClientSecret(): ?string { return $this->clientSecret; }
    public function setClientSecret(?string $v): void { $this->clientSecret = $v; }

    public function getScope(): ?string { return $this->scope; }
    public function setScope(?string $v): void { $this->scope = $v; }

    public function getTenantId(): ?string { return $this->tenantId; }
    public function setTenantId(?string $v): void { $this->tenantId = $v; }

    public function getDiscount(): ?float { return $this->discount; }
    public function setDiscount(?float $v): void { $this->discount = $v; }

    public function getDiscountType(): ?string { return $this->discountType; }
    public function setDiscountType(?string $v): void { $this->discountType = $v; }

    public function getOpType(): ?string { return $this->opType; }
    public function setOpType(?string $v): void { $this->opType = $v; }

    public function getIsPreferAdmin(): ?bool { return $this->isPreferAdmin; }
    public function setIsPreferAdmin(?bool $v): void { $this->isPreferAdmin = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }
}
