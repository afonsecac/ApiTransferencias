<?php

namespace App\DTO;

class AccountBalanceSeriesDto
{
    private string $name;

    /**
     * @var \App\DTO\AccountBalanceCordDto[]
     */
    private array $data;

    /**
     * @param string $name
     * @param \App\DTO\AccountBalanceCordDto[] $data
     */
    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}