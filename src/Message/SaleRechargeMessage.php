<?php

namespace App\Message;

use App\Entity\CommunicationSaleRecharge;

class SaleRechargeMessage
{
    private int $saleId;

    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }

    public function getSaleId(): int
    {
        return $this->saleId;
    }
}