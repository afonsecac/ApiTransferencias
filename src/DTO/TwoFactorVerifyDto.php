<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorVerifyDto implements IInput
{
    #[OAProperty(description: 'Token temporal devuelto por `/login` en el campo `pendingToken` cuando `requires2fa=true`. Expira en 10 minutos.')]
    #[Assert\NotBlank]
    protected ?string $pendingToken = null;

    #[OAProperty(description: 'Código de 6 dígitos: generado por la app autenticadora (TOTP) o recibido por correo electrónico.')]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 6)]
    protected ?string $code = null;

    public function __construct(
        ?string $pendingToken = null,
        ?string $code = null,
    ) {
        $this->pendingToken = $pendingToken;
        $this->code         = $code;
    }

    public function getPendingToken(): ?string { return $this->pendingToken; }
    public function setPendingToken(?string $v): void { $this->pendingToken = $v; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): void { $this->code = $v; }
}
