<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorCodeDto implements IInput
{
    #[OAProperty(description: 'Código de 6 dígitos: generado por la app autenticadora (TOTP) o recibido por correo electrónico.')]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 6)]
    protected ?string $code = null;

    public function __construct(?string $code = null)
    {
        $this->code = $code;
    }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): void { $this->code = $v; }
}
