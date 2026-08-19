<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCommunicationPackageDto implements IInput
{
    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    #[Assert\Positive]
    protected ?float $destinationAmount;

    #[Assert\Length(max: 10)]
    protected ?string $destinationCurrency;

    protected ?bool $isActive;

    protected ?string $activeStartAt;

    protected ?string $activeEndAt;

    #[Assert\PositiveOrZero]
    protected ?int $displayOrder;

    protected ?array $benefits;

    protected ?array $tags;

    protected ?array $service;

    protected ?array $validity;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $knowMore = null,
        ?float $destinationAmount = null,
        ?string $destinationCurrency = null,
        ?bool $isActive = null,
        ?string $activeStartAt = null,
        ?string $activeEndAt = null,
        ?int $displayOrder = null,
        ?array $benefits = null,
        ?array $tags = null,
        ?array $service = null,
        ?array $validity = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->knowMore = $knowMore;
        $this->destinationAmount = $destinationAmount;
        $this->destinationCurrency = $destinationCurrency;
        $this->isActive = $isActive;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt = $activeEndAt;
        $this->displayOrder = $displayOrder;
        $this->benefits = $benefits;
        $this->tags = $tags;
        $this->service = $service;
        $this->validity = $validity;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getDestinationAmount(): ?float { return $this->destinationAmount; }
    public function setDestinationAmount(?float $v): void { $this->destinationAmount = $v; }

    public function getDestinationCurrency(): ?string { return $this->destinationCurrency; }
    public function setDestinationCurrency(?string $v): void { $this->destinationCurrency = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }

    public function getDisplayOrder(): ?int { return $this->displayOrder; }
    public function setDisplayOrder(?int $v): void { $this->displayOrder = $v; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $v): void { $this->benefits = $v; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $v): void { $this->tags = $v; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $v): void { $this->service = $v; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $v): void { $this->validity = $v; }
}
