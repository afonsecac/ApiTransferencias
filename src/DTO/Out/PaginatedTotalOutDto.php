<?php

namespace App\DTO\Out;

final class PaginatedTotalOutDto
{
    public int $total;
    public int $page;
    public int $perPage;
    public array $results = [];
}
