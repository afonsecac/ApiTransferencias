<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCommunicationProductDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $packageId;

    #[Assert\NotNull]
    #[Assert\Length(max: 255)]
    protected ?string $packageType;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $price;

    #[Assert\NotNull]
    protected ?bool $enabled;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $environmentId;

    #[Assert\Length(max: 255)]
    protected ?string $productType;

    #[Assert\Length(max: 255)]
    protected ?string $description;

    protected ?string $initialDate;

    protected ?string $endDateAt;

    public function __construct(
        ?int $packageId = null,
        ?string $packageType = null,
        ?float $price = null,
        ?bool $enabled = null,
        ?int $environmentId = null,
        ?string $productType = null,
        ?string $description = null,
        ?string $initialDate = null,
        ?string $endDateAt = null,
    ) {
        $this->packageId     = $packageId;
        $this->packageType   = $packageType;
        $this->price         = $price;
        $this->enabled       = $enabled;
        $this->environmentId = $environmentId;
        $this->productType   = $productType;
        $this->description   = $description;
        $this->initialDate   = $initialDate;
        $this->endDateAt     = $endDateAt;
    }

    public function getPackageId(): ?int { return $this->packageId; }
    public function setPackageId(?int $v): void { $this->packageId = $v; }

    public function getPackageType(): ?string { return $this->packageType; }
    public function setPackageType(?string $v): void { $this->packageType = $v; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getEnabled(): ?bool { return $this->enabled; }
    public function setEnabled(?bool $v): void { $this->enabled = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    public function getProductType(): ?string { return $this->productType; }
    public function setProductType(?string $v): void { $this->productType = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getInitialDate(): ?string { return $this->initialDate; }
    public function setInitialDate(?string $v): void { $this->initialDate = $v; }

    public function getEndDateAt(): ?string { return $this->endDateAt; }
    public function setEndDateAt(?string $v): void { $this->endDateAt = $v; }
}
