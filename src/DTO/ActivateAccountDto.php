<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ActivateAccountDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    protected ?string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 15)]
    protected ?string $code;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    protected ?string $password;

    #[Assert\NotBlank]
    protected ?string $passwordConfirm;

    public function __construct(
        ?string $email = null,
        ?string $code = null,
        ?string $password = null,
        ?string $passwordConfirm = null,
    ) {
        $this->email           = $email;
        $this->code            = $code;
        $this->password        = $password;
        $this->passwordConfirm = $passwordConfirm;
    }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): void { $this->email = $v; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): void { $this->code = $v; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): void { $this->password = $v; }

    public function getPasswordConfirm(): ?string { return $this->passwordConfirm; }
    public function setPasswordConfirm(?string $v): void { $this->passwordConfirm = $v; }
}
