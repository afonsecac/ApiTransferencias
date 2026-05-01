<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCommunicationPriceDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?float $startPrice;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?float $amount;

    protected ?float $endPrice;

    #[Assert\Length(exactly: 3)]
    protected ?string $currencyPrice;

    #[Assert\Length(exactly: 3)]
    protected ?string $currency;

    protected ?bool $isActive;

    protected ?string $validStartAt;

    protected ?string $validEndAt;

    public function __construct(
        ?float $startPrice = null,
        ?float $amount = null,
        ?float $endPrice = null,
        ?string $currencyPrice = null,
        ?string $currency = null,
        ?bool $isActive = null,
        ?string $validStartAt = null,
        ?string $validEndAt = null,
    ) {
        $this->startPrice   = $startPrice;
        $this->amount       = $amount;
        $this->endPrice     = $endPrice;
        $this->currencyPrice = $currencyPrice;
        $this->currency     = $currency;
        $this->isActive     = $isActive;
        $this->validStartAt = $validStartAt;
        $this->validEndAt   = $validEndAt;
    }

    public function getStartPrice(): ?float { return $this->startPrice; }
    public function setStartPrice(?float $v): void { $this->startPrice = $v; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(?float $v): void { $this->amount = $v; }

    public function getEndPrice(): ?float { return $this->endPrice; }
    public function setEndPrice(?float $v): void { $this->endPrice = $v; }

    public function getCurrencyPrice(): ?string { return $this->currencyPrice; }
    public function setCurrencyPrice(?string $v): void { $this->currencyPrice = $v; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): void { $this->currency = $v; }

    public function getIsActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $v): void { $this->isActive = $v; }

    public function getValidStartAt(): ?string { return $this->validStartAt; }
    public function setValidStartAt(?string $v): void { $this->validStartAt = $v; }

    public function getValidEndAt(): ?string { return $this->validEndAt; }
    public function setValidEndAt(?string $v): void { $this->validEndAt = $v; }
}
