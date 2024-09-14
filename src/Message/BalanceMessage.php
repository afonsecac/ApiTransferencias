<?php

namespace App\Message;

class BalanceMessage
{
    private string $messageType;
    private float $currentBalance;
    private string $currency;
    private int $accountId;

    /**
     * @param string $messageType
     * @param float $currentBalance
     * @param string $currency
     * @param int $accountId
     */
    public function __construct(string $messageType, float $currentBalance, string $currency, int $accountId)
    {
        $this->messageType = $messageType;
        $this->currentBalance = $currentBalance;
        $this->currency = $currency;
        $this->accountId = $accountId;
    }


    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }
}