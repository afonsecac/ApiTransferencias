<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/products/{id}/status: publicar o retirar. Un producto nunca vuelve a borrador.
 */
class ChangeProductStatusDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['draft', 'published', 'withdrawn'])]
    protected ?string $status;

    public function __construct(?string $status = null)
    {
        $this->status = $status;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }
}
