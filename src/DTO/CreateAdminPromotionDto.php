<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAdminPromotionDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\NotNull]
    protected ?int $productId;

    #[Assert\NotBlank]
    protected ?string $startAt;

    #[Assert\NotBlank]
    protected ?string $endAt;

    #[Assert\Length(max: 500)]
    protected ?string $knowMore;

    protected ?string $infoDescription;
    protected ?array  $terms;
    protected ?array  $validity;
    protected ?array  $products;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?int    $productId = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?string $knowMore = null,
        ?string $infoDescription = null,
        ?array  $terms = null,
        ?array  $validity = null,
        ?array  $products = null,
    ) {
        $this->name            = $name;
        $this->description     = $description;
        $this->productId       = $productId;
        $this->startAt         = $startAt;
        $this->endAt           = $endAt;
        $this->knowMore        = $knowMore;
        $this->infoDescription = $infoDescription;
        $this->terms           = $terms;
        $this->validity        = $validity;
        $this->products        = $products;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $productId): void { $this->productId = $productId; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $startAt): void { $this->startAt = $startAt; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $endAt): void { $this->endAt = $endAt; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $knowMore): void { $this->knowMore = $knowMore; }

    public function getInfoDescription(): ?string { return $this->infoDescription; }
    public function setInfoDescription(?string $infoDescription): void { $this->infoDescription = $infoDescription; }

    public function getTerms(): ?array { return $this->terms; }
    public function setTerms(?array $terms): void { $this->terms = $terms; }

    public function getValidity(): ?array { return $this->validity; }
    public function setValidity(?array $validity): void { $this->validity = $validity; }

    public function getProducts(): ?array { return $this->products; }
    public function setProducts(?array $products): void { $this->products = $products; }
}
