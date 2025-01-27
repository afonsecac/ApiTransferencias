<?php

namespace App\DTO;

class AccountBalanceCordDto
{
    private \DateTimeImmutable $x;
    private float $y;

    /**
     * @param \DateTimeImmutable $x
     * @param float $y
     */
    public function __construct(\DateTimeImmutable $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getX(): \DateTimeImmutable
    {
        return $this->x;
    }

    public function setX(\DateTimeImmutable $x): void
    {
        $this->x = $x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): void
    {
        $this->y = $y;
    }
}