<?php

namespace App\DTO;

class BudgetInfoDto
{
    private float $data;
    private ?float $goals;
    private ?float $limits;
    private ?float $expected;

    /**
     * @param float $data
     * @param float|null $goals
     * @param float|null $limits
     * @param float|null $expected
     */
    public function __construct(float $data, ?float $goals, ?float $limits, ?float $expected)
    {
        $this->data = $data;
        $this->goals = $goals;
        $this->limits = $limits;
        $this->expected = $expected;
    }

    public function getData(): float
    {
        return $this->data;
    }

    public function setData(float $data): void
    {
        $this->data = $data;
    }

    public function getGoals(): ?float
    {
        return $this->goals;
    }

    public function setGoals(?float $goals): void
    {
        $this->goals = $goals;
    }

    public function getLimits(): ?float
    {
        return $this->limits;
    }

    public function setLimits(?float $limits): void
    {
        $this->limits = $limits;
    }

    public function getExpected(): ?float
    {
        return $this->expected;
    }

    public function setExpected(?float $expected): void
    {
        $this->expected = $expected;
    }
}