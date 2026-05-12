<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

class TwoFactorSetupOutDto
{
    #[OAProperty(schema: ['type' => 'string', 'enum' => ['totp', 'email']], description: 'Método de configuración iniciado según la política global.')]
    public string $method;

    #[OAProperty(description: 'Secreto Base32 para configurar manualmente la app autenticadora. Solo presente cuando `method=totp`.')]
    public ?string $secret = null;

    #[OAProperty(description: 'URI `otpauth://` para generar el QR que escanea la app autenticadora. Solo presente cuando `method=totp`.')]
    public ?string $otpUri = null;

    #[OAProperty(description: 'Mensaje informativo. Solo presente cuando `method=email` (indica que el código fue enviado al correo).')]
    public ?string $message = null;
}
