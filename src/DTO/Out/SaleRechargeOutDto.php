<?php

namespace App\DTO\Out;

final class SaleRechargeOutDto
{
    private ?ResultDto $Result;
    private ?SaleRechargeDto $SaleRecharge;

    /**
     * @param \App\DTO\Out\ResultDto|null $Result
     * @param \App\DTO\Out\SaleRechargeDto|null $SaleRecharge
     */
    public function __construct(?ResultDto $Result, ?SaleRechargeDto $SaleRecharge)
    {
        $this->Result = $Result;
        $this->SaleRecharge = $SaleRecharge;
    }

    public function getResult(): ?ResultDto
    {
        return $this->Result;
    }

    public function setResult(?ResultDto $Result): void
    {
        $this->Result = $Result;
    }

    public function getSaleRecharge(): ?SaleRechargeDto
    {
        return $this->SaleRecharge;
    }

    public function setSaleRecharge(?SaleRechargeDto $SaleRecharge): void
    {
        $this->SaleRecharge = $SaleRecharge;
    }
}