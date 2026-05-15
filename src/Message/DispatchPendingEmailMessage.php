<?php

namespace App\Message;

class DispatchPendingEmailMessage
{
    public function __construct(
        private readonly int $recharges,
        private readonly int $packages,
        private readonly int $total,
        private readonly string $dispatchedAt,
        private readonly ?string $triggeredBy,
        private readonly array $transactionIds,
    ) {}

    public function getRecharges(): int { return $this->recharges; }
    public function getPackages(): int { return $this->packages; }
    public function getTotal(): int { return $this->total; }
    public function getDispatchedAt(): string { return $this->dispatchedAt; }
    public function getTriggeredBy(): ?string { return $this->triggeredBy; }
    public function getTransactionIds(): array { return $this->transactionIds; }
}
