<?php

namespace App\DTO;

use App\DTO\IInput;

class BalanceInDto implements IInput
{
    protected ?int $id;
    protected ?float $amount;
    protected ?string $currency;
    protected ?bool $isAdvance;
    protected ?bool $isRequired;
    protected ?bool $isImpugned;
    protected ?string $comment;
    protected ?float $amountApproved;
    protected ?string $currencyApproved;
    protected ?int $accountId;
    protected ?int $environmentId;

    /**
     * @param int|null $id
     * @param float|null $amount
     * @param string|null $currency
     * @param bool|null $isAdvance
     * @param bool|null $isRequired
     * @param bool|null $isImpugned
     * @param string|null $comment
     * @param float|null $amountApproved
     * @param string|null $currencyApproved
     * @param int|null $accountId
     * @param int|null $environmentId
     */
    public function __construct(
        ?int $id,
        ?float $amount,
        ?string $currency,
        ?bool $isAdvance,
        ?bool $isRequired,
        ?bool $isImpugned,
        ?string $comment,
        ?float $amountApproved,
        ?string $currencyApproved,
        ?int $accountId,
        ?int $environmentId
    ) {
        $this->id = $id;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->isAdvance = $isAdvance;
        $this->isRequired = $isRequired;
        $this->isImpugned = $isImpugned;
        $this->comment = $comment;
        $this->amountApproved = $amountApproved;
        $this->currencyApproved = $currencyApproved;
        $this->accountId = $accountId;
        $this->environmentId = $environmentId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getIsAdvance(): ?bool
    {
        return $this->isAdvance;
    }

    public function setIsAdvance(?bool $isAdvance): void
    {
        $this->isAdvance = $isAdvance;
    }

    public function getIsRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(?bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }

    public function getIsImpugned(): ?bool
    {
        return $this->isImpugned;
    }

    public function setIsImpugned(?bool $isImpugned): void
    {
        $this->isImpugned = $isImpugned;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getAmountApproved(): ?float
    {
        return $this->amountApproved;
    }

    public function setAmountApproved(?float $amountApproved): void
    {
        $this->amountApproved = $amountApproved;
    }

    public function getCurrencyApproved(): ?string
    {
        return $this->currencyApproved;
    }

    public function setCurrencyApproved(?string $currencyApproved): void
    {
        $this->currencyApproved = $currencyApproved;
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function setAccountId(?int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function getEnvironmentId(): ?int
    {
        return $this->environmentId;
    }

    public function setEnvironmentId(?int $environmentId): void
    {
        $this->environmentId = $environmentId;
    }


}