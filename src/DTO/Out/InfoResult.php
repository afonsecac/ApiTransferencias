<?php

namespace App\DTO\Out;

final class InfoResult
{
    private ?SaleRechargeOutDto $saleRecharge;
    private ?SaleStateDto $saleState;
    private ?SaleDto $sale;

    /**
     * @param \App\DTO\Out\SaleRechargeOutDto|null $saleRecharge
     * @param \App\DTO\Out\SaleStateDto|null $saleState
     * @param \App\DTO\Out\SaleDto|null $sale
     */
    public function __construct(?SaleRechargeOutDto $saleRecharge, ?SaleStateDto $saleState, ?SaleDto $sale)
    {
        $this->saleRecharge = $saleRecharge;
        $this->saleState = $saleState;
        $this->sale = $sale;
    }

    public function getSaleRecharge(): ?SaleRechargeOutDto
    {
        return $this->saleRecharge;
    }

    public function setSaleRecharge(?SaleRechargeOutDto $saleRecharge): void
    {
        $this->saleRecharge = $saleRecharge;
    }

    public function getSaleState(): ?SaleStateDto
    {
        return $this->saleState;
    }

    public function setSaleState(?SaleStateDto $saleState): void
    {
        $this->saleState = $saleState;
    }

    public function getSale(): ?SaleDto
    {
        return $this->sale;
    }

    public function setSale(?SaleDto $sale): void
    {
        $this->sale = $sale;
    }
}