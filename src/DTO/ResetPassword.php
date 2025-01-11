<?php

namespace App\DTO;

class ResetPassword
{
    private string $password;
    private string $passwordConfirm;
    private string $code;
    private string $email;

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getPasswordConfirm(): string
    {
        return $this->passwordConfirm;
    }

    public function setPasswordConfirm(string $passwordConfirm): void
    {
        $this->passwordConfirm = $passwordConfirm;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }


}