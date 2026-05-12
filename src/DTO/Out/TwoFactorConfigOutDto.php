<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

class TwoFactorConfigOutDto
{
    #[OAProperty(schema: ['type' => 'string', 'enum' => ['optional', 'mandatory']], description: 'Política global: `optional` nadie está obligado; `mandatory` todos deben usar 2FA a partir de `deadline`.')]
    public string $mode;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['totp', 'email']], description: 'Método de verificación activo: `totp` app autenticadora, `email` código enviado al correo.')]
    public string $method;

    #[OAProperty(description: 'Fecha límite ISO 8601 desde la que el 2FA es exigido (solo aplica con `mode=mandatory`). `null` si no se ha configurado.')]
    public ?string $deadline;
}
