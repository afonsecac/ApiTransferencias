<?php

namespace App\DTO\Out;

final class SaleDto
{
    private ?ResultDto $Result;
    private ?SalePackageDto $Sale;

    /**
     * @param \App\DTO\Out\ResultDto|null $Result
     * @param \App\DTO\Out\SalePackageDto|null $Sale
     */
    public function __construct(?ResultDto $Result, ?SalePackageDto $Sale)
    {
        $this->Result = $Result;
        $this->Sale = $Sale;
    }

    public function getResult(): ?ResultDto
    {
        return $this->Result;
    }

    public function setResult(?ResultDto $Result): void
    {
        $this->Result = $Result;
    }

    public function getSale(): ?SalePackageDto
    {
        return $this->Sale;
    }

    public function setSale(?SalePackageDto $Sale): void
    {
        $this->Sale = $Sale;
    }


}