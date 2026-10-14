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
    /** Unidad organizativa de tipo comprador a la que pertenece (COMR-272); nula si aún no está vinculada. */
    public ?ClientOrgUnitOutDto $orgUnit = null;
    /** @var AccountSecOutDto[] */
    public array $accounts = [];
}
