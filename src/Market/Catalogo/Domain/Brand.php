<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Marca del mercado (COMR-70, T6): una taxonomía con tabla propia que pertenece a un vendedor (COMR-171: entidades separadas).
 * El identificador (slug) es único entre todas las marcas y, igual que el vendedor, no cambia tras crearla. No se borra: se
 * desactiva, y desactivarla no la quita de los productos que la usan, solo la oculta de los selectores.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_brand')]
#[ORM\UniqueConstraint(name: 'uniq_market_brand_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_market_brand_seller', columns: ['seller_id'])]
class Brand
{
    public const NAME_MAX_LENGTH = 160;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'seller_id', type: 'integer')]
    private int $sellerId;

    #[ORM\Column(type: 'string', length: 60)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(int $sellerId, Slug $slug, string $name)
    {
        if ($sellerId <= 0) {
            throw new InvalidBrand('Una marca no se guarda sin vendedor.');
        }
        $this->sellerId = $sellerId;
        $this->slug = $slug->value();
        $this->name = self::validName($name);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(int $sellerId, Slug $slug, string $name): self
    {
        return new self($sellerId, $slug, $name);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sellerId(): int
    {
        return $this->sellerId;
    }

    public function slug(): Slug
    {
        return new Slug($this->slug);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::validName($name);
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->touch();
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidBrand('La marca necesita un nombre.');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidBrand(sprintf('El nombre de la marca admite como mucho %d caracteres.', self::NAME_MAX_LENGTH));
        }

        return $name;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
