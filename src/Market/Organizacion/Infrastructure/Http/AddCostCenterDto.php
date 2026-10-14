<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/org-units/{id}/cost-centers. Un centro nuevo nunca es el principal.
 */
class AddCostCenterDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', message: 'El código admite letras, números, guion y guion bajo.')]
    protected ?string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $name;

    public function __construct(?string $code = null, ?string $name = null)
    {
        $this->code = $code;
        $this->name = $name;
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
}
