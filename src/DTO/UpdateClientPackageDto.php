<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateClientPackageDto implements IInput
{
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
        $this->name          = $name;
        $this->description   = $description;
        $this->amount        = $amount;
        $this->currency      = $currency;
        $this->activeStartAt = $activeStartAt;
        $this->activeEndAt   = $activeEndAt;
        $this->knowMore      = $knowMore;
        $this->benefits      = $benefits;
        $this->tags          = $tags;
        $this->service       = $service;
        $this->destination   = $destination;
        $this->validity      = $validity;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $v): void { $this->amount = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getActiveStartAt(): ?string { return $this->activeStartAt; }
    public function setActiveStartAt(?string $v): void { $this->activeStartAt = $v; }

    public function getActiveEndAt(): ?string { return $this->activeEndAt; }
    public function setActiveEndAt(?string $v): void { $this->activeEndAt = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getBenefits(): ?array { return $this->benefits; }
    public function setBenefits(?array $v): void { $this->benefits = $v; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $v): void { $this->tags = $v; }

    public function getService(): ?array { return $this->service; }
    public function setService(?array $v): void { $this->service = $v; }

    public function getDestination(): ?array { return $this->destination; }
    public function setDestination(?array $v): void { $this->destination = $v; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $v): void { $this->validity = $v; }
}
