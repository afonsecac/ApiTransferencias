<?php

namespace App\Message;

class ForgotPasswordMessage
{
    private string $email;
    private string $code;
    private string $origin;
    private string $name;

    /**
     * @param string $email
     * @param string $code
     * @param string $origin
     * @param string $name
     */
    public function __construct(string $email, string $code, string $origin, string $name)
    {
        $this->email = $email;
        $this->code = $code;
        $this->origin = $origin;
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getName(): string
    {
        return $this->name;
    }
}