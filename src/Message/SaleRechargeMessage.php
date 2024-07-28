<?php

namespace App\Message;

use App\Entity\CommunicationSaleRecharge;

class SaleRechargeMessage
{
    private int $saleId;
    private CommunicationSaleRecharge $sale;

    /**
     * @param int $saleId
     * @param \App\Entity\CommunicationSaleRecharge $sale
     */
    public function __construct(int $saleId, CommunicationSaleRecharge $sale)
    {
        $this->saleId = $saleId;
        $this->sale = $sale;
    }

    public function getSaleId(): int
    {
        return $this->saleId;
    }

    public function getSale(): CommunicationSaleRecharge
    {
        return $this->sale;
    }
}