<?php

namespace App\DTO\Out;

final class ClientSecOutDto
{
    public int $id;
    public string $companyName;
    public string $companyCountry;
    public string $companyIdentification;
    public string $companyEmail;
    public bool $isActive;
    /** @var AccountSecOutDto[] */
    public array $accounts = [];
}
