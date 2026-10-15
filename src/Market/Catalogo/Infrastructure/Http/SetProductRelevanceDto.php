<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/products/{id}/relevance (COMR-269): nivel de 0 (sin destacar) a 3 y vigencia opcional
 * por días (AAAA-MM-DD, ambos extremos incluidos). Con nivel 0 se quita la marca.
 */
class SetProductRelevanceDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 3)]
    protected ?int $level;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Debe tener el formato AAAA-MM-DD.')]
    protected ?string $from;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Debe tener el formato AAAA-MM-DD.')]
    protected ?string $until;

    public function __construct(?int $level = null, ?string $from = null, ?string $until = null)
    {
        $this->level = $level;
        $this->from = $from;
        $this->until = $until;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): void
    {
        $this->level = $level;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    public function getUntil(): ?string
    {
        return $this->until;
    }

    public function setUntil(?string $until): void
    {
        $this->until = $until;
    }
}
