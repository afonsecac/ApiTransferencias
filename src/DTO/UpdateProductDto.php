<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductDto implements IInput
{
    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\Length(max: 255)]
    protected ?string $packageType;

    #[Assert\Length(max: 255)]
    protected ?string $productType;

    #[Assert\PositiveOrZero]
    protected ?float $price;

    protected ?string $initialDate;

    protected ?string $endDateAt;

    #[Assert\Positive]
    protected ?int $packageId;

    protected ?bool $enabled;

    #[Assert\Positive]
    protected ?int $environmentId;

    public function __construct(
        ?string $description = null,
        ?string $packageType = null,
        ?string $productType = null,
        ?float $price = null,
        ?string $initialDate = null,
        ?string $endDateAt = null,
        ?int $packageId = null,
        ?bool $enabled = null,
        ?int $environmentId = null,
    ) {
        $this->description   = $description;
        $this->packageType   = $packageType;
        $this->productType   = $productType;
        $this->price         = $price;
        $this->initialDate   = $initialDate;
        $this->endDateAt     = $endDateAt;
        $this->packageId     = $packageId;
        $this->enabled       = $enabled;
        $this->environmentId = $environmentId;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getPackageType(): ?string { return $this->packageType; }
    public function setPackageType(?string $v): void { $this->packageType = $v; }

    public function getProductType(): ?string { return $this->productType; }
    public function setProductType(?string $v): void { $this->productType = $v; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): void { $this->price = $v; }

    public function getInitialDate(): ?string { return $this->initialDate; }
    public function setInitialDate(?string $v): void { $this->initialDate = $v; }

    public function getEndDateAt(): ?string { return $this->endDateAt; }
    public function setEndDateAt(?string $v): void { $this->endDateAt = $v; }

    public function getPackageId(): ?int { return $this->packageId; }
    public function setPackageId(?int $v): void { $this->packageId = $v; }

    public function getEnabled(): ?bool { return $this->enabled; }
    public function setEnabled(?bool $v): void { $this->enabled = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }
}
