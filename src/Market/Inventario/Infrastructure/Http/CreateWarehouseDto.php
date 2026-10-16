<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/warehouses. Un almacén nace en borrador con solo nombre e identificador; el identificador
 * es opcional y por defecto se deriva del nombre.
 */
class CreateWarehouseDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\Length(max: 60)]
    protected ?string $slug;

    public function __construct(?string $name = null, ?string $slug = null)
    {
        $this->name = $name;
        $this->slug = $slug;
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
