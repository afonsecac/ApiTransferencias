<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePricePackageDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $price;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $priceCurrency;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $amount;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $tenantId;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $productId;

    protected ?int $priceUsedId;

    protected ?int $environmentId;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    protected ?bool $isActive;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    public function __construct(
        ?float $price = null,
        ?string $priceCurrency = null,
        ?float $amount = null,
        ?string $currency = null,
        ?int $tenantId = null,
        ?int $productId = null,
        ?int $priceUsedId = null,
        ?int $environmentId = null,
        ?string $name = null,
        ?bool $isActive = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
        ?string $description = null,
    ) {
        $this->price         = $price;
        $this->priceCurrency = $priceCurrency;
        $this->amount        = $amount;
        $this->currency      = $currency;
        $this->tenantId      = $tenantId;
        $this->productId     = $productId;
        $this->priceUsedId   = $priceUsedId;
        $this->environmentId = $environmentId;
        $this->name          = $name;
        $this->isActive      = $isActive;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt   = $activeEndAt;
        $this->description   = $description;
    }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getPriceCurrency(): ?string { return $this->priceCurrency; }
    public function setPriceCurrency(?string $v): void { $this->priceCurrency = $v; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $v): void { $this->amount = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): void { $this->tenantId = $v; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): void { $this->productId = $v; }

    public function getPriceUsedId(): ?int { return $this->priceUsedId; }
    public function setPriceUsedId(?int $v): void { $this->priceUsedId = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }
}
