<?php

namespace App\DTO\Out;

final class AuthTokenOutDto
{
    public string $token;
    public int $expiresIn;
    public ?UserOutDto $user = null;
}
