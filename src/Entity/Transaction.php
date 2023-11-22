<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ApiResource]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $accountId = null;

    #[ORM\Column]
    private ?int $beneficiaryId = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?int $transactionType = null;

    #[ORM\Column]
    private ?int $senderId = null;

    #[ORM\Column]
    private ?int $processorType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function setAccountId(int $accountId): static
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function getBeneficiaryId(): ?int
    {
        return $this->beneficiaryId;
    }

    public function setBeneficiaryId(int $beneficiaryId): static
    {
        $this->beneficiaryId = $beneficiaryId;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTransactionType(): ?int
    {
        return $this->transactionType;
    }

    public function setTransactionType(int $transactionType): static
    {
        $this->transactionType = $transactionType;

        return $this;
    }

    public function getSenderId(): ?int
    {
        return $this->senderId;
    }

    public function setSenderId(int $senderId): static
    {
        $this->senderId = $senderId;

        return $this;
    }

    public function getProcessorType(): ?int
    {
        return $this->processorType;
    }

    public function setProcessorType(int $processorType): static
    {
        $this->processorType = $processorType;

        return $this;
    }
}
