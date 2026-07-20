<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateClientDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $companyName = null;

    #[Assert\Length(max: 255)]
    protected ?string $companyAddress = null;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $companyCountry = null;

    #[Assert\Length(max: 12)]
    protected ?string $companyZipCode = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 120)]
    protected ?string $companyEmail = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $companyPhoneNumber = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $discountOfClient = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $companyIdentification = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $companyIdentificationType = null;

    protected ?float $minBalance = null;

    protected ?float $criticalBalance = null;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency = null;

    protected ?bool $isAlert = null;

    #[Assert\Length(max: 255)]
    protected ?string $contractWith = null;

    #[Assert\Length(exactly: 3)]
    protected ?string $contractCurrency = null;

    protected ?bool $isActive = null;

    public function __construct(
        ?string $companyName = null,
        ?string $companyAddress = null,
        ?string $companyCountry = null,
        ?string $companyZipCode = null,
        ?string $companyEmail = null,
        ?string $companyPhoneNumber = null,
        ?float  $discountOfClient = null,
        ?string $companyIdentification = null,
        ?string $companyIdentificationType = null,
        ?float  $minBalance = null,
        ?float  $criticalBalance = null,
        ?string $currency = null,
        ?bool   $isAlert = null,
        ?string $contractWith = null,
        ?string $contractCurrency = null,
        ?bool   $isActive = null,
    ) {
        $this->companyName               = $companyName;
        $this->companyAddress            = $companyAddress;
        $this->companyCountry            = $companyCountry;
        $this->companyZipCode            = $companyZipCode;
        $this->companyEmail              = $companyEmail;
        $this->companyPhoneNumber        = $companyPhoneNumber;
        $this->discountOfClient          = $discountOfClient;
        $this->companyIdentification     = $companyIdentification;
        $this->companyIdentificationType = $companyIdentificationType;
        $this->minBalance                = $minBalance;
        $this->criticalBalance           = $criticalBalance;
        $this->currency                  = $currency;
        $this->isAlert                   = $isAlert;
        $this->contractWith              = $contractWith;
        $this->contractCurrency          = $contractCurrency;
        $this->isActive                  = $isActive;
    }

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $v): void { $this->companyName = $v; }

    public function getCompanyAddress(): ?string { return $this->companyAddress; }
    public function setCompanyAddress(?string $v): void { $this->companyAddress = $v; }

    public function getCompanyCountry(): ?string { return $this->companyCountry; }
    public function setCompanyCountry(?string $v): void { $this->companyCountry = $v; }

    public function getCompanyZipCode(): ?string { return $this->companyZipCode; }
    public function setCompanyZipCode(?string $v): void { $this->companyZipCode = $v; }

    public function getCompanyEmail(): ?string { return $this->companyEmail; }
    public function setCompanyEmail(?string $v): void { $this->companyEmail = $v; }

    public function getCompanyPhoneNumber(): ?string { return $this->companyPhoneNumber; }
    public function setCompanyPhoneNumber(?string $v): void { $this->companyPhoneNumber = $v; }

    public function getDiscountOfClient(): ?float { return $this->discountOfClient; }
    public function setDiscountOfClient(?float $v): void { $this->discountOfClient = $v; }

    public function getCompanyIdentification(): ?string { return $this->companyIdentification; }
    public function setCompanyIdentification(?string $v): void { $this->companyIdentification = $v; }

    public function getCompanyIdentificationType(): ?string { return $this->companyIdentificationType; }
    public function setCompanyIdentificationType(?string $v): void { $this->companyIdentificationType = $v; }

    public function getMinBalance(): ?float { return $this->minBalance; }
    public function setMinBalance(?float $v): void { $this->minBalance = $v; }

    public function getCriticalBalance(): ?float { return $this->criticalBalance; }
    public function setCriticalBalance(?float $v): void { $this->criticalBalance = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getIsAlert(): ?bool { return $this->isAlert; }
    public function setIsAlert(?bool $v): void { $this->isAlert = $v; }

    public function getContractWith(): ?string { return $this->contractWith; }
    public function setContractWith(?string $v): void { $this->contractWith = $v; }

    public function getContractCurrency(): ?string { return $this->contractCurrency; }
    public function setContractCurrency(?string $v): void { $this->contractCurrency = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }
}
