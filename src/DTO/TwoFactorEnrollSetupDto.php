<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorEnrollSetupDto implements IInput
{
    #[OAProperty(description: 'Token temporal devuelto por `/login` en el campo `pendingToken` cuando `requiresEnrollment=true`. Expira en 10 minutos.')]
    #[Assert\NotBlank]
    protected ?string $pendingToken = null;

    public function __construct(
        ?string $pendingToken = null,
    ) {
        $this->pendingToken = $pendingToken;
    }

    public function getPendingToken(): ?string { return $this->pendingToken; }
    public function setPendingToken(?string $v): void { $this->pendingToken = $v; }
}
