<?php

namespace App\DTO\Out;

final class ClientOutDto
{
    public int $id;
    public string $companyName;
    public ?string $companyAddress = null;
    public string $companyCountry;
    public ?string $companyZipCode = null;
    public string $companyEmail;
    public string $companyPhoneNumber;
    public float $discountOfClient;
    public string $companyIdentification;
    public string $companyIdentificationType;
    public ?float $minBalance = null;
    public ?float $criticalBalance = null;
    public ?string $currency = null;
    public ?bool $isAlert = null;
    public ?string $contractWith = null;
    public ?bool $isActive = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
