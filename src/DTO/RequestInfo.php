<?php

namespace App\DTO;

use App\DTO\IInput;

final class RequestInfo implements IInput
{
    private string $type;
    private ?string $clientTxId;
    private ?int $internalTxId;

    /**
     * @param string $type
     * @param string|null $clientTxId
     * @param int|null $internalTxId
     */
    public function __construct(string $type, ?string $clientTxId, ?int $internalTxId)
    {
        $this->type = $type;
        $this->clientTxId = $clientTxId;
        $this->internalTxId = $internalTxId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getClientTxId(): ?string
    {
        return $this->clientTxId;
    }

    public function setClientTxId(?string $clientTxId): void
    {
        $this->clientTxId = $clientTxId;
    }

    public function getInternalTxId(): ?int
    {
        return $this->internalTxId;
    }

    public function setInternalTxId(?int $internalTxId): void
    {
        $this->internalTxId = $internalTxId;
    }
}