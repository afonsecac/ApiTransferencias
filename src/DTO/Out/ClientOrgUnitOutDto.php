<?php

namespace App\DTO\Out;

final class ClientOrgUnitOutDto
{
    public int $id;
    public string $name;
    public string $countryCode;
    public ?string $countryCodeIso3 = null;
    public string $taxId;
    /** @var string[] */
    public array $types = [];
}
