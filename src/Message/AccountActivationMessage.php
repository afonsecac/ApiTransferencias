<?php

namespace App\Message;

class AccountActivationMessage
{
    public function __construct(
        private readonly string $email,
        private readonly string $code,
        private readonly ?string $contractWith,
        private readonly ?string $firstName,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getContractWith(): ?string
    {
        return $this->contractWith;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
}
