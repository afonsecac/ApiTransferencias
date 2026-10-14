<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/categories. Sin padre es una categoría raíz; el árbol admite hasta tres niveles. El
 * identificador (slug) es opcional y por defecto se deriva del nombre en español.
 */
class CreateCategoryDto implements IInput
{
    #[Assert\Positive]
    protected ?int $parentId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $nameEs;

    #[Assert\Length(max: 120)]
    protected ?string $nameEn;

    #[Assert\Length(max: 60)]
    protected ?string $slug;

    #[Assert\PositiveOrZero]
    protected ?int $position;

    public function __construct(?int $parentId = null, ?string $nameEs = null, ?string $nameEn = null, ?string $slug = null, ?int $position = null)
    {
        $this->parentId = $parentId;
        $this->nameEs = $nameEs;
        $this->nameEn = $nameEn;
        $this->slug = $slug;
        $this->position = $position;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getNameEs(): ?string
    {
        return $this->nameEs;
    }

    public function setNameEs(?string $nameEs): void
    {
        $this->nameEs = $nameEs;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): void
    {
        $this->nameEn = $nameEn;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }
}
