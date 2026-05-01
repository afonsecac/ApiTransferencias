<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdatePromotionDto implements IInput
{
    protected ?string $name;

    protected ?string $description;

    protected ?string $infoDescription;

    protected ?string $knowMore;

    protected ?array $terms;

    protected ?array $validityInfo;

    protected ?string $startAt;

    protected ?string $endAt;

    #[Assert\Positive]
    protected ?int $productId;

    protected ?array $environment;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $infoDescription = null,
        ?string $knowMore = null,
        ?array $terms = null,
        ?array $validityInfo = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?int $productId = null,
        ?array $environment = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->infoDescription = $infoDescription;
        $this->knowMore = $knowMore;
        $this->terms = $terms;
        $this->validityInfo = $validityInfo;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->productId = $productId;
        $this->environment = $environment;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getInfoDescription(): ?string { return $this->infoDescription; }
    public function setInfoDescription(?string $v): void { $this->infoDescription = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getTerms(): ?array { return $this->terms; }
    public function setTerms(?array $v): void { $this->terms = $v; }

    public function getValidityInfo(): ?array { return $this->validityInfo; }
    public function setValidityInfo(?array $v): void { $this->validityInfo = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): void { $this->productId = $v; }

    public function getEnvironment(): ?array { return $this->environment; }
    public function setEnvironment(?array $v): void { $this->environment = $v; }
}
