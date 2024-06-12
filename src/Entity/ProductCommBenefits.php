<?php

namespace App\Entity;

use App\Repository\ProductCommBenefitsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductCommBenefitsRepository::class)]
class ProductCommBenefits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductComm $productCommId = null;

    #[ORM\Column(length: 20)]
    private ?string $benefitType = null;

    #[ORM\Column(length: 20)]
    private ?string $benefitUnitType = null;

    #[ORM\Column(length: 50)]
    private ?string $benefitUnit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $benefitDescription = null;

    #[ORM\Column]
    private ?float $baseInfo = null;

    #[ORM\Column]
    private ?float $promotionBonus = null;

    #[ORM\Column]
    private ?float $totalWithTax = null;

    #[ORM\Column]
    private ?float $totalWithoutTax = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validUntilAt = null;

    public function __construct()
    {
        $this->baseInfo = 0;
        $this->promotionBonus = 0;
        $this->totalWithTax = 0;
        $this->totalWithoutTax = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductCommId(): ?ProductComm
    {
        return $this->productCommId;
    }

    public function setProductCommId(?ProductComm $productCommId): static
    {
        $this->productCommId = $productCommId;

        return $this;
    }

    public function getBenefitType(): ?string
    {
        return $this->benefitType;
    }

    public function setBenefitType(string $benefitType): static
    {
        $this->benefitType = $benefitType;

        return $this;
    }

    public function getBenefitUnitType(): ?string
    {
        return $this->benefitUnitType;
    }

    public function setBenefitUnitType(string $benefitUnitType): static
    {
        $this->benefitUnitType = $benefitUnitType;

        return $this;
    }

    public function getBenefitUnit(): ?string
    {
        return $this->benefitUnit;
    }

    public function setBenefitUnit(string $benefitUnit): static
    {
        $this->benefitUnit = $benefitUnit;

        return $this;
    }

    public function getBenefitDescription(): ?string
    {
        return $this->benefitDescription;
    }

    public function setBenefitDescription(?string $benefitDescription): static
    {
        $this->benefitDescription = $benefitDescription;

        return $this;
    }

    public function getBaseInfo(): ?float
    {
        return $this->baseInfo;
    }

    public function setBaseInfo(float $baseInfo): static
    {
        $this->baseInfo = $baseInfo;

        return $this;
    }

    public function getPromotionBonus(): ?float
    {
        return $this->promotionBonus;
    }

    public function setPromotionBonus(float $promotionBonus): static
    {
        $this->promotionBonus = $promotionBonus;

        return $this;
    }

    public function getTotalWithTax(): ?float
    {
        return $this->totalWithTax;
    }

    public function setTotalWithTax(float $totalWithTax): static
    {
        $this->totalWithTax = $totalWithTax;

        return $this;
    }

    public function getTotalWithoutTax(): ?float
    {
        return $this->totalWithoutTax;
    }

    public function setTotalWithoutTax(float $totalWithoutTax): static
    {
        $this->totalWithoutTax = $totalWithoutTax;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getValidUntilAt(): ?\DateTimeImmutable
    {
        return $this->validUntilAt;
    }

    public function setValidUntilAt(?\DateTimeImmutable $validUntilAt): static
    {
        $this->validUntilAt = $validUntilAt;

        return $this;
    }
}
