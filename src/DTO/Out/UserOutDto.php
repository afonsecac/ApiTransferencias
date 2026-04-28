<?php

namespace App\DTO\Out;

final class UserOutDto
{
    public int $id;
    public string $email;
    public string $firstName;
    public ?string $middleName = null;
    public string $lastName;
    public array $roles = [];
    public ?string $jobTitle = null;
    public ?string $phoneNumber = null;
    public bool $isActive;
    public bool $isCheckValidation;
    public ?CompanyRefOutDto $company = null;
    public ?string $createdAt = null;
    public ?string $removedAt = null;
}
