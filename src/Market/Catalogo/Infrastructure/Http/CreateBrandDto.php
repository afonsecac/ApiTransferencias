<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/brands. La marca pertenece a un vendedor; el identificador (slug) es opcional y por
 * defecto se deriva del nombre.
 */
class CreateBrandDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $sellerId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\Length(max: 60)]
    protected ?string $slug;

    public function __construct(?int $sellerId = null, ?string $name = null, ?string $slug = null)
    {
        $this->sellerId = $sellerId;
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    public function setSellerId(?int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }
}
