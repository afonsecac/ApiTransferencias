<?php

namespace App\DTO\Out;

final class PaginatedListOutDto
{
    public int $limit;
    public int $currentPage;
    public bool $hasNext;
    public bool $hasPrevious;
    public array $results = [];
}
