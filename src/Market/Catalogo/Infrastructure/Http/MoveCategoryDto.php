<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/categories/{id}/move: el nuevo padre, o null para llevarla a la raíz. Se mueve con todo
 * su subárbol; no puede colgar de sí misma ni de una descendiente, ni pasar de tres niveles.
 */
class MoveCategoryDto implements IInput
{
    #[Assert\Positive]
    protected ?int $parentId;

    public function __construct(?int $parentId = null)
    {
        $this->parentId = $parentId;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
    }
}
