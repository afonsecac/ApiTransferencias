<?php

namespace App\DTO\Out;

final class SaleRechargeDto
{
    private string $TransactionID;
    private string $PhoneNumber;
    private float $Price;
    private \DateTimeImmutable $Date;
    private string $RechargeStateCode;
    private string $RechargeState;
    private string $ProductName;
    private string $ProductCurrency;
    private string $SaleState;
    private string $Message;

    /**
     * @param string $TransactionID
     * @param string $PhoneNumber
     * @param float $Price
     * @param \DateTimeImmutable $Date
     * @param string $RechargeStateCode
     * @param string $RechargeState
     * @param string $ProductName
     * @param string $ProductCurrency
     * @param string $SaleState
     * @param string $Message
     */
    public function __construct(
        string $TransactionID,
        string $PhoneNumber,
        float $Price,
        \DateTimeImmutable $Date,
        string $RechargeStateCode,
        string $RechargeState,
        string $ProductName,
        string $ProductCurrency,
        string $SaleState,
        string $Message
    ) {
        $this->TransactionID = $TransactionID;
        $this->PhoneNumber = $PhoneNumber;
        $this->Price = $Price;
        $this->Date = $Date;
        $this->RechargeStateCode = $RechargeStateCode;
        $this->RechargeState = $RechargeState;
        $this->ProductName = $ProductName;
        $this->ProductCurrency = $ProductCurrency;
        $this->SaleState = $SaleState;
        $this->Message = $Message;
    }

    public function getTransactionID(): string
    {
        return $this->TransactionID;
    }

    public function setTransactionID(string $TransactionID): void
    {
        $this->TransactionID = $TransactionID;
    }

    public function getPhoneNumber(): string
    {
        return $this->PhoneNumber;
    }

    public function setPhoneNumber(string $PhoneNumber): void
    {
        $this->PhoneNumber = $PhoneNumber;
    }

    public function getPrice(): float
    {
        return $this->Price;
    }

    public function setPrice(float $Price): void
    {
        $this->Price = $Price;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->Date;
    }

    public function setDate(\DateTimeImmutable $Date): void
    {
        $this->Date = $Date;
    }

    public function getRechargeStateCode(): string
    {
        return $this->RechargeStateCode;
    }

    public function setRechargeStateCode(string $RechargeStateCode): void
    {
        $this->RechargeStateCode = $RechargeStateCode;
    }

    public function getRechargeState(): string
    {
        return $this->RechargeState;
    }

    public function setRechargeState(string $RechargeState): void
    {
        $this->RechargeState = $RechargeState;
    }

    public function getProductName(): string
    {
        return $this->ProductName;
    }

    public function setProductName(string $ProductName): void
    {
        $this->ProductName = $ProductName;
    }

    public function getProductCurrency(): string
    {
        return $this->ProductCurrency;
    }

    public function setProductCurrency(string $ProductCurrency): void
    {
        $this->ProductCurrency = $ProductCurrency;
    }

    public function getSaleState(): string
    {
        return $this->SaleState;
    }

    public function setSaleState(string $SaleState): void
    {
        $this->SaleState = $SaleState;
    }

    public function getMessage(): string
    {
        return $this->Message;
    }

    public function setMessage(string $Message): void
    {
        $this->Message = $Message;
    }




}