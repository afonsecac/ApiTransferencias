<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorBackupCodeDto implements IInput
{
    #[OAProperty(description: 'Token temporal devuelto por `/login` en el campo `pendingToken`. Expira en 10 minutos.')]
    #[Assert\NotBlank]
    protected ?string $pendingToken = null;

    #[OAProperty(description: 'Código de respaldo con formato `XXXX-XXXX`, de los entregados al activar el 2FA. Se acepta con o sin guion y sin distinguir mayúsculas. Es de un solo uso.')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 9)]
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
