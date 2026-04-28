<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpsertPromotionDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $description;

    #[Assert\NotBlank]
    protected ?string $startAt;

    #[Assert\NotBlank]
    protected ?string $endAt;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountFrom;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountTo;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $amountStep;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $productId;

    protected ?array  $environment;
    protected ?array  $terms;
    protected ?string $infoDescription;
    protected ?string $knowMore;
    protected ?array  $validityInfo;
    protected ?array  $clients;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?string $currency = null,
        ?float  $amountFrom = null,
        ?float  $amountTo = null,
        ?float  $amountStep = null,
        ?int    $productId = null,
        ?array  $environment = null,
        ?array  $terms = null,
        ?string $infoDescription = null,
        ?string $knowMore = null,
        ?array  $validityInfo = null,
        ?array  $clients = null,
    ) {
        $this->name            = $name;
        $this->description     = $description;
        $this->startAt         = $startAt;
        $this->endAt           = $endAt;
        $this->currency        = $currency;
        $this->amountFrom      = $amountFrom;
        $this->amountTo        = $amountTo;
        $this->amountStep      = $amountStep;
        $this->productId       = $productId;
        $this->environment     = $environment;
        $this->terms           = $terms;
        $this->infoDescription = $infoDescription;
        $this->knowMore        = $knowMore;
        $this->validityInfo    = $validityInfo;
        $this->clients         = $clients;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): void { $this->description = $v; }

    public function getStartAt(): ?string { return $this->startAt; }
    public function setStartAt(?string $v): void { $this->startAt = $v; }

    public function getEndAt(): ?string { return $this->endAt; }
    public function setEndAt(?string $v): void { $this->endAt = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getAmountFrom(): ?float { return $this->amountFrom; }
    public function setAmountFrom(?float $v): void { $this->amountFrom = $v; }

    public function getAmountTo(): ?float { return $this->amountTo; }
    public function setAmountTo(?float $v): void { $this->amountTo = $v; }

    public function getAmountStep(): ?float { return $this->amountStep; }
    public function setAmountStep(?float $v): void { $this->amountStep = $v; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): void { $this->productId = $v; }

    public function getEnvironment(): ?array { return $this->environment; }
    public function setEnvironment(?array $v): void { $this->environment = $v; }

    public function getTerms(): ?array { return $this->terms; }
    public function setTerms(?array $v): void { $this->terms = $v; }

    public function getInfoDescription(): ?string { return $this->infoDescription; }
    public function setInfoDescription(?string $v): void { $this->infoDescription = $v; }

    public function getKnowMore(): ?string { return $this->knowMore; }
    public function setKnowMore(?string $v): void { $this->knowMore = $v; }

    public function getValidityInfo(): ?array { return $this->validityInfo; }
    public function setValidityInfo(?array $v): void { $this->validityInfo = $v; }

    public function getClients(): ?array { return $this->clients; }
    public function setClients(?array $v): void { $this->clients = $v; }
}
