<?php

namespace App\DTO;

class UpdateUserPermissionDto implements IInput
{
    protected ?string $minRoleRequired;

    protected ?bool $isActive;

    protected ?int $clientId;

    protected ?int $userId;

    public function __construct(
        ?string $minRoleRequired = null,
        ?bool $isActive = null,
        ?int $clientId = null,
        ?int $userId = null,
    ) {
        $this->minRoleRequired = $minRoleRequired;
        $this->isActive        = $isActive;
        $this->clientId        = $clientId;
        $this->userId          = $userId;
    }

    public function getMinRoleRequired(): ?string { return $this->minRoleRequired; }
    public function setMinRoleRequired(?string $v): void { $this->minRoleRequired = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getClientId(): ?int { return $this->clientId; }
    public function setClientId(?int $v): void { $this->clientId = $v; }

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $v): void { $this->userId = $v; }
}
