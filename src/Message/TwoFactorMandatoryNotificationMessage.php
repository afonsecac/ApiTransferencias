<?php

namespace App\Message;

class TwoFactorMandatoryNotificationMessage
{
    public function __construct(
        private readonly string  $email,
        private readonly string  $firstName,
        private readonly string  $deadline,
        private readonly ?string $contractWith,
    ) {}

    public function getEmail(): string { return $this->email; }
    public function getFirstName(): string { return $this->firstName; }
    public function getDeadline(): string { return $this->deadline; }
    public function getContractWith(): ?string { return $this->contractWith; }
}
