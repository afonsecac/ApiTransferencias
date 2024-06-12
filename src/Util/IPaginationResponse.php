<?php

namespace App\Util;

interface IPaginationResponse
{
    public function getPage(): int;
    public function getLimit(): int;
    public function getTotal(): int;
    public function getTotalPages(): int;
    public function getResults(): array;
}
