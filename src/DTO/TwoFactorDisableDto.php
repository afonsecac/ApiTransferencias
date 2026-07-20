<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorDisableDto implements IInput
{
    #[OAProperty(description: 'Contraseña actual del usuario. Se exige para desactivar el 2FA de modo que un token de sesión robado no baste para apagar el segundo factor.')]
    #[Assert\NotBlank]
    protected ?string $password = null;

    public function __construct(
        ?string $password = null,
    ) {
        $this->password = $password;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): void { $this->password = $v; }
}
