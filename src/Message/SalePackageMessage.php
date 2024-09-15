<?php

namespace App\Message;

final class SalePackageMessage
{
    private int $saleId;

    /**
     * @param int $saleId
     */
    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }

    public function getSaleId(): int
    {
        return $this->saleId;
    }
}