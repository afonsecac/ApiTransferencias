<?php

namespace App\EntityPaginator;

use App\Entity\CommunicationProduct;
use App\Util\IPaginationResponse;

class PaginatorResponse implements IPaginationResponse
{
    private int $page;
    private int $limit;
    private int $total;
    private array $products;

    /**
     * @param int $page
     * @param int $limit
     * @param int $total
     * @param array $products
     */
    public function __construct(int $page, int $limit, int $total, array $products)
    {
        $this->page = $page;
        $this->limit = $limit;
        $this->total = $total;
        $this->products = $products;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->limit);
    }

    public function getResults(): array
    {
        return $this->products;
    }
}
