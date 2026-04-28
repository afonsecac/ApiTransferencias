<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAccountSecurityDto implements IInput
{
    protected ?string $origin;

    #[Assert\PositiveOrZero]
    protected ?float $minBalance;

    #[Assert\PositiveOrZero]
    protected ?float $criticalBalance;

    protected ?bool $isActive;

    #[Assert\PositiveOrZero]
    protected ?float $discount;

    #[Assert\PositiveOrZero]
    protected ?float $commission;

    public function __construct(
        ?string $origin = null,
        ?float  $minBalance = null,
        ?float  $criticalBalance = null,
        ?bool   $isActive = null,
        ?float  $discount = null,
        ?float  $commission = null,
    ) {
        $this->origin          = $origin;
        $this->minBalance      = $minBalance;
        $this->criticalBalance = $criticalBalance;
        $this->isActive        = $isActive;
        $this->discount        = $discount;
        $this->commission      = $commission;
    }

    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $v): void { $this->origin = $v; }

    public function getMinBalance(): ?float { return $this->minBalance; }
    public function setMinBalance(?float $v): void { $this->minBalance = $v; }

    public function getCriticalBalance(): ?float { return $this->criticalBalance; }
    public function setCriticalBalance(?float $v): void { $this->criticalBalance = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getDiscount(): ?float { return $this->discount; }
    public function setDiscount(?float $v): void { $this->discount = $v; }

    public function getCommission(): ?float { return $this->commission; }
    public function setCommission(?float $v): void { $this->commission = $v; }
}
