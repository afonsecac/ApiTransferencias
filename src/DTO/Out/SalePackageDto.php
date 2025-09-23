<?php

namespace App\DTO\Out;

final class SalePackageDto
{
    private ?int $OrderId;
    private ?string $TransactionId;
    private ?string $State;
    private ?string $ItemStates;
    private ?ClientDto $Client;
    private ?string $PhoneNumber;
    private ?\DateTimeImmutable $CreatedDateTime;
    private ?\DateTimeImmutable $ExpiredDateTime;
    private ?\DateTimeImmutable $ProcessedDateTime;
    private ?\DateTimeImmutable $ExecutedDateTime;
    private ?int $Code;
    private ?string $Package;

    /**
     * @param int|null $OrderId
     * @param string|null $TransactionId
     * @param string|null $State
     * @param string|null $ItemStates
     * @param \App\DTO\Out\ClientDto|null $Client
     * @param string|null $PhoneNumber
     * @param \DateTimeImmutable|null $CreatedDateTime
     * @param \DateTimeImmutable|null $ExpiredDateTime
     * @param \DateTimeImmutable|null $ProcessedDateTime
     * @param \DateTimeImmutable|null $ExecutedDateTime
     * @param int|null $Code
     * @param string|null $Package
     */
    public function __construct(
        ?int $OrderId,
        ?string $TransactionId,
        ?string $State,
        ?string $ItemStates,
        ?ClientDto $Client,
        ?string $PhoneNumber,
        ?\DateTimeImmutable $CreatedDateTime,
        ?\DateTimeImmutable $ExpiredDateTime,
        ?\DateTimeImmutable $ProcessedDateTime,
        ?\DateTimeImmutable $ExecutedDateTime,
        ?int $Code,
        ?string $Package
    ) {
        $this->OrderId = $OrderId;
        $this->TransactionId = $TransactionId;
        $this->State = $State;
        $this->ItemStates = $ItemStates;
        $this->Client = $Client;
        $this->PhoneNumber = $PhoneNumber;
        $this->CreatedDateTime = $CreatedDateTime;
        $this->ExpiredDateTime = $ExpiredDateTime;
        $this->ProcessedDateTime = $ProcessedDateTime;
        $this->ExecutedDateTime = $ExecutedDateTime;
        $this->Code = $Code;
        $this->Package = $Package;
    }

    public function getOrderId(): ?int
    {
        return $this->OrderId;
    }

    public function setOrderId(?int $OrderId): void
    {
        $this->OrderId = $OrderId;
    }

    public function getTransactionId(): ?string
    {
        return $this->TransactionId;
    }

    public function setTransactionId(?string $TransactionId): void
    {
        $this->TransactionId = $TransactionId;
    }

    public function getState(): ?string
    {
        return $this->State;
    }

    public function setState(?string $State): void
    {
        $this->State = $State;
    }

    public function getItemStates(): ?string
    {
        return $this->ItemStates;
    }

    public function setItemStates(?string $ItemStates): void
    {
        $this->ItemStates = $ItemStates;
    }

    public function getClient(): ?ClientDto
    {
        return $this->Client;
    }

    public function setClient(?ClientDto $Client): void
    {
        $this->Client = $Client;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->PhoneNumber;
    }

    public function setPhoneNumber(?string $PhoneNumber): void
    {
        $this->PhoneNumber = $PhoneNumber;
    }

    public function getCreatedDateTime(): ?\DateTimeImmutable
    {
        return $this->CreatedDateTime;
    }

    public function setCreatedDateTime(?\DateTimeImmutable $CreatedDateTime): void
    {
        $this->CreatedDateTime = $CreatedDateTime;
    }

    public function getExpiredDateTime(): ?\DateTimeImmutable
    {
        return $this->ExpiredDateTime;
    }

    public function setExpiredDateTime(?\DateTimeImmutable $ExpiredDateTime): void
    {
        $this->ExpiredDateTime = $ExpiredDateTime;
    }

    public function getProcessedDateTime(): ?\DateTimeImmutable
    {
        return $this->ProcessedDateTime;
    }

    public function setProcessedDateTime(?\DateTimeImmutable $ProcessedDateTime): void
    {
        $this->ProcessedDateTime = $ProcessedDateTime;
    }

    public function getExecutedDateTime(): ?\DateTimeImmutable
    {
        return $this->ExecutedDateTime;
    }

    public function setExecutedDateTime(?\DateTimeImmutable $ExecutedDateTime): void
    {
        $this->ExecutedDateTime = $ExecutedDateTime;
    }

    public function getCode(): ?int
    {
        return $this->Code;
    }

    public function setCode(?int $Code): void
    {
        $this->Code = $Code;
    }

    public function getPackage(): ?string
    {
        return $this->Package;
    }

    public function setPackage(?string $Package): void
    {
        $this->Package = $Package;
    }
}