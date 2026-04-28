<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateClientPackageDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $tenantId;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $priceClientPackageId;

    protected ?int $environmentId;

    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\PositiveOrZero]
    protected ?float $amount;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    protected ?array $benefits;

    protected ?array $tags;

    protected ?array $service;

    protected ?array $destination;

    protected ?array $validity;

    public function __construct(
        ?int $tenantId = null,
        ?int $priceClientPackageId = null,
        ?int $environmentId = null,
        ?string $name = null,
        ?string $description = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
        ?string $knowMore = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $destination = null,
        ?array $validity = null,
    ) {
        $this->tenantId = $tenantId;
        $this->priceClientPackageId = $priceClientPackageId;
        $this->environmentId = $environmentId;
        $this->name = $name;
        $this->description = $description;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
        $this->knowMore = $knowMore;
        $this->benefits = $benefits;
        $this->tags = $tags;
        $this->service = $service;
        $this->destination = $destination;
        $this->validity = $validity;
    }

    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $tenantId): void { $this->tenantId = $tenantId; }

    public function getPriceClientPackageId(): ?int { return $this->priceClientPackageId; }
    public function setPriceClientPackageId(?int $priceClientPackageId): void { $this->priceClientPackageId = $priceClientPackageId; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $environmentId): void { $this->environmentId = $environmentId; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $amount): void { $this->amount = $amount; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): void { $this->currency = $currency; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $activeStartAt): void { $this->activeStartAt = $activeStartAt; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $activeEndAt): void { $this->activeEndAt = $activeEndAt; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $knowMore): void { $this->knowMore = $knowMore; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $benefits): void { $this->benefits = $benefits; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $tags): void { $this->tags = $tags; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $service): void { $this->service = $service; }

    public function getDestination(): ?array { return $this->destination; }
    public function setDestination(?array $destination): void { $this->destination = $destination; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $validity): void { $this->validity = $validity; }
}
