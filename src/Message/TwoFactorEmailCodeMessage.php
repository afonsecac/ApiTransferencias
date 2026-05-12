<?php

namespace App\Message;

class TwoFactorEmailCodeMessage
{
    public function __construct(
        private readonly string  $email,
        private readonly string  $firstName,
        private readonly string  $code,
        private readonly ?string $contractWith,
    ) {}

    public function getEmail(): string { return $this->email; }
    public function getFirstName(): string { return $this->firstName; }
    public function getCode(): string { return $this->code; }
    public function getContractWith(): ?string { return $this->contractWith; }
}
