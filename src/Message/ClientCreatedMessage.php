<?php

namespace App\Message;

class ClientCreatedMessage
{
    public function __construct(
        private readonly string  $email,
        private readonly string  $companyName,
        private readonly string  $accessToken,
        private readonly string  $environmentType,
        private readonly ?string $contractWith,
        private readonly ?string $origin,
    ) {}

    public function getEmail(): string           { return $this->email; }
    public function getCompanyName(): string      { return $this->companyName; }
    public function getAccessToken(): string      { return $this->accessToken; }
    public function getEnvironmentType(): string  { return $this->environmentType; }
    public function getContractWith(): ?string    { return $this->contractWith; }
    public function getOrigin(): ?string          { return $this->origin; }
}
