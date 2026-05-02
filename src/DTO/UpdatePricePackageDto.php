<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdatePricePackageDto implements IInput
{
    #[Assert\Positive]
    protected ?float $price;

    #[Assert\Length(exactly: 3)]
    protected ?string $priceCurrency;

    #[Assert\PositiveOrZero]
    protected ?float $amount;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    protected ?bool $isActive;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    public function __construct(
        ?float $price = null,
        ?string $priceCurrency = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $name = null,
        ?string $description = null,
        ?bool $isActive = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
    ) {
        $this->price         = $price;
        $this->priceCurrency = $priceCurrency;
        $this->amount        = $amount;
        $this->currency      = $currency;
        $this->name          = $name;
        $this->description   = $description;
        $this->isActive      = $isActive;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt   = $activeEndAt;
    }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getPriceCurrency(): ?string { return $this->priceCurrency; }
    public function setPriceCurrency(?string $v): void { $this->priceCurrency = $v; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $v): void { $this->amount = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }
}
