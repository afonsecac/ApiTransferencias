<?php

namespace App\DTO\Out;

final class ResultDto
{
    private bool $ValueOk;
    private string $Message;
    private ?string $Code;
    private ?\DateTimeImmutable $RequestTime;
    private ?\DateTimeImmutable $ResponseTime;

    /**
     * @param bool $ValueOk
     * @param string $Message
     * @param string|null $Code
     * @param \DateTimeImmutable|null $RequestTime
     * @param \DateTimeImmutable|null $ResponseTime
     */
    public function __construct(
        bool $ValueOk,
        string $Message,
        ?string $Code,
        ?\DateTimeImmutable $RequestTime,
        ?\DateTimeImmutable $ResponseTime
    ) {
        $this->ValueOk = $ValueOk;
        $this->Message = $Message;
        $this->Code = $Code;
        $this->RequestTime = $RequestTime;
        $this->ResponseTime = $ResponseTime;
    }

    public function isValueOk(): bool
    {
        return $this->ValueOk;
    }

    public function setValueOk(bool $ValueOk): void
    {
        $this->ValueOk = $ValueOk;
    }

    public function getMessage(): string
    {
        return $this->Message;
    }

    public function setMessage(string $Message): void
    {
        $this->Message = $Message;
    }

    public function getCode(): ?string
    {
        return $this->Code;
    }

    public function setCode(?string $Code): void
    {
        $this->Code = $Code;
    }

    public function getRequestTime(): ?\DateTimeImmutable
    {
        return $this->RequestTime;
    }

    public function setRequestTime(?\DateTimeImmutable $RequestTime): void
    {
        $this->RequestTime = $RequestTime;
    }

    public function getResponseTime(): ?\DateTimeImmutable
    {
        return $this->ResponseTime;
    }

    public function setResponseTime(?\DateTimeImmutable $ResponseTime): void
    {
        $this->ResponseTime = $ResponseTime;
    }
}