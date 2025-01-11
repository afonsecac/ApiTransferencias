<?php

namespace App\DTO;

class AccountBalanceInfoDto
{
    private float $growRate;
    private float $ami;

    /**
     * @var AccountBalanceSeriesDto[]
     */
    private array $series;

    /**
     * @param float $growRate
     * @param float $ami
     * @param \App\DTO\AccountBalanceSeriesDto[] $series
     */
    public function __construct(float $growRate, float $ami, array $series = [])
    {
        $this->growRate = $growRate;
        $this->ami = $ami;
        $this->series = $series;
    }

    public function getGrowRate(): float
    {
        return $this->growRate;
    }

    public function setGrowRate(float $growRate): void
    {
        $this->growRate = $growRate;
    }

    public function getAmi(): float
    {
        return $this->ami;
    }

    public function setAmi(float $ami): void
    {
        $this->ami = $ami;
    }

    /**
     * @return \App\DTO\AccountBalanceSeriesDto[]
     */
    public function getSeries(): array
    {
        return $this->series;
    }

    /**
     * @param \App\DTO\AccountBalanceSeriesDto[] $series
     * @return void
     */
    public function setSeries(array $series): void
    {
        $this->series = $series;
    }

}