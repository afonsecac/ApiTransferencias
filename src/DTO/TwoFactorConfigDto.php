<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorConfigDto implements IInput
{
    #[OAProperty(schema: ['type' => 'string', 'enum' => ['optional', 'mandatory'], 'nullable' => true], description: '`optional` — el 2FA está disponible pero no es obligatorio. `mandatory` — se exige a todos los usuarios a partir de `deadline`.')]
    #[Assert\Choice(choices: ['optional', 'mandatory'])]
    protected ?string $mode = null;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['totp', 'email'], 'nullable' => true], description: '`totp` — app autenticadora (Google Authenticator, Authy…). `email` — código de 6 dígitos enviado al correo del usuario.')]
    #[Assert\Choice(choices: ['totp', 'email'])]
    protected ?string $method = null;

    #[OAProperty(description: 'Fecha límite (ISO 8601, ej. `2026-06-15`) a partir de la cual el 2FA se vuelve obligatorio cuando `mode=mandatory`. Mientras la fecha sea futura, el 2FA sigue siendo opcional aunque `mode` ya sea `mandatory`. Al guardar con `mode=mandatory` y un deadline, se envía automáticamente un email de aviso a todos los usuarios sin 2FA activado.')]
    protected ?string $deadline = null;

    public function __construct(
        ?string $mode = null,
        ?string $method = null,
        ?string $deadline = null,
    ) {
        $this->mode     = $mode;
        $this->method   = $method;
        $this->deadline = $deadline;
    }

    public function getMode(): ?string { return $this->mode; }
    public function setMode(?string $v): void { $this->mode = $v; }

    public function getMethod(): ?string { return $this->method; }
    public function setMethod(?string $v): void { $this->method = $v; }

    public function getDeadline(): ?string { return $this->deadline; }
    public function setDeadline(?string $v): void { $this->deadline = $v; }
}
