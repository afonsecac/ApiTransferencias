<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un centro de costo del body de alta de una unidad. Anidado en CreateOrgUnitDto.
 */
class CostCenterDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', message: 'El código admite letras, números, guion y guion bajo.')]
    protected ?string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $name;

    protected ?bool $principal;

    public function __construct(?string $code = null, ?string $name = null, ?bool $principal = null)
    {
        $this->code = $code;
        $this->name = $name;
        $this->principal = $principal;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPrincipal(): ?bool
    {
        return $this->principal;
    }

    public function setPrincipal(?bool $principal): void
    {
        $this->principal = $principal;
    }
}
