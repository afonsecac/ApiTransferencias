<?php

namespace App\DTO\Out;

final class ProfileUpdateOutDto
{
    public string $token;
    public ?UserOutDto $user = null;
}
