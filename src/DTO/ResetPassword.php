<?php

namespace App\DTO;

final class ResetPassword implements IInput
{
    private string $password;
    private string $passwordConfirm;
    private string $code;
    private string $email;

    /**
     * @param string $password
     * @param string $passwordConfirm
     * @param string $code
     * @param string $email
     */
    public function __construct(string $password, string $passwordConfirm, string $code, string $email)
    {
        $this->password = $password;
        $this->passwordConfirm = $passwordConfirm;
        $this->code = $code;
        $this->email = $email;
    }

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