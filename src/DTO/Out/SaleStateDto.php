<?php

namespace App\DTO\Out;

final class SaleStateDto
{
    private ?ResultDto $Result;
    private ?string $State;

    /**
     * @param \App\DTO\Out\ResultDto|null $Result
     * @param string|null $State
     */
    public function __construct(?ResultDto $Result, ?string $State)
    {
        $this->Result = $Result;
        $this->State = $State;
    }

    public function getResult(): ?ResultDto
    {
        return $this->Result;
    }

    public function setResult(?ResultDto $Result): void
    {
        $this->Result = $Result;
    }

    public function getState(): ?string
    {
        return $this->State;
    }

    public function setState(?string $State): void
    {
        $this->State = $State;
    }
}