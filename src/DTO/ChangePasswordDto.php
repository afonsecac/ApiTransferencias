<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDto implements IInput
{
    #[Assert\NotBlank]
    protected ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    protected ?string $newPassword = null;

    #[Assert\NotBlank]
    protected ?string $confirmPassword = null;

    public function __construct(
        ?string $currentPassword = null,
        ?string $newPassword = null,
        ?string $confirmPassword = null,
    ) {
        $this->currentPassword = $currentPassword;
        $this->newPassword     = $newPassword;
        $this->confirmPassword = $confirmPassword;
    }

    public function getCurrentPassword(): ?string { return $this->currentPassword; }
    public function setCurrentPassword(?string $v): void { $this->currentPassword = $v; }

    public function getNewPassword(): ?string { return $this->newPassword; }
    public function setNewPassword(?string $v): void { $this->newPassword = $v; }

    public function getConfirmPassword(): ?string { return $this->confirmPassword; }
    public function setConfirmPassword(?string $v): void { $this->confirmPassword = $v; }
}
