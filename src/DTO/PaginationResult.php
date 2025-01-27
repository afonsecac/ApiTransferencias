<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

class PaginationResult
{
    #[Groups(['reports:list'])]
    private int $total;
    #[Groups(['reports:list'])]
    private int $page;
    #[Groups(['reports:list'])]
    private int $perPage;
    #[Groups(['reports:list'])]
    private array $results;

    /**
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param array $results
     */
    public function __construct(int $total, int $page, int $perPage, array $results)
    {
        $this->total = $total;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->results = $results;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }
}