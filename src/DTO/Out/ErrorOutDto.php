<?php

namespace App\DTO\Out;

final class ErrorOutDto
{
    public string $message;
    /** @var string[]|null */
    public ?array $details = null;
}
