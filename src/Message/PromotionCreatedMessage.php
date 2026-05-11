<?php

declare(strict_types=1);

namespace App\Message;

class PromotionCreatedMessage
{
    public function __construct(
        private readonly int $promotionId,
        private readonly string $recipientEmail,
        private readonly ?string $recipientFirstName,
        private readonly int $clientId,
        private readonly ?string $contractWith,
    ) {}

    public function getPromotionId(): int { return $this->promotionId; }
    public function getRecipientEmail(): string { return $this->recipientEmail; }
    public function getRecipientFirstName(): ?string { return $this->recipientFirstName; }
    public function getClientId(): int { return $this->clientId; }
    public function getContractWith(): ?string { return $this->contractWith; }
}
