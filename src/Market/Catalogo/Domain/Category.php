<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

use App\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;

/**
 * Categoría del mercado (COMR-70, T6): una taxonomía con tabla propia, en árbol de hasta tres niveles (`parent_id`, sin ruta
 * materializada, COMR-174) y con nombre en español (obligatorio) e inglés. Es global de la plataforma, no de un vendedor
 * (propuesta de Jira). Su identificador (slug) es único e inmutable. No se borra: se desactiva, y desactivarla no la quita de
 * los productos que la usan, solo la oculta de los selectores.
 *
 * La profundidad y los ciclos no los decide la categoría sola, sino el árbol (CategoryTree).
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_category')]
#[ORM\UniqueConstraint(name: 'uniq_market_category_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_market_category_parent', columns: ['parent_id'])]
class Category
{
    public const NAME_MAX_LENGTH = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 60)]
    private string $slug;

    #[ORM\Column(name: 'parent_id', type: 'integer', nullable: true)]
    private ?int $parentId;

    #[ORM\Column(name: 'name_es', type: 'string', length: 120)]
    private string $nameEs;

    #[ORM\Column(name: 'name_en', type: 'string', length: 120, nullable: true)]
    private ?string $nameEn;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(Slug $slug, ?int $parentId, string $nameEs, ?string $nameEn, int $position)
    {
        $this->slug = $slug->value();
        $this->parentId = $parentId;
        $this->setNames($nameEs, $nameEn);
        $this->setPosition($position);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(Slug $slug, ?int $parentId, string $nameEs, ?string $nameEn, int $position = 0): self
    {
        return new self($slug, $parentId, $nameEs, $nameEn, $position);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function slug(): Slug
    {
        return new Slug($this->slug);
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function nameEs(): string
    {
        return $this->nameEs;
    }

    public function nameEn(): ?string
    {
        return $this->nameEn;
    }

    public function position(): int
    {
        return $this->position;
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

    /** El identificador y el padre no cambian aquí: el padre se cambia con moveTo(), que valida el árbol. */
    public function update(string $nameEs, ?string $nameEn, int $position): void
    {
        $this->setNames($nameEs, $nameEn);
        $this->setPosition($position);
        $this->touch();
    }

    /**
     * Solo cambia el padre; que no se cree un ciclo ni se pase de tres niveles lo comprueba CategoryTree antes de llamar.
     *
     * @throws InvalidCategory si se pone ella misma de padre
     */
    public function moveTo(?int $parentId): void
    {
        if ($parentId !== null && $parentId === $this->id) {
            throw new InvalidCategory('Una categoría no puede ser su propio padre.');
        }
        $this->parentId = $parentId;
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

    private function setNames(string $nameEs, ?string $nameEn): void
    {
        $nameEs = trim($nameEs);
        if ($nameEs === '') {
            throw new InvalidCategory('El nombre en español es obligatorio.');
        }
        $nameEn = $nameEn === null || trim($nameEn) === '' ? null : trim($nameEn);
        foreach ([$nameEs, $nameEn] as $name) {
            if ($name !== null && mb_strlen($name) > self::NAME_MAX_LENGTH) {
                throw new InvalidCategory(sprintf('Los nombres admiten como mucho %d caracteres.', self::NAME_MAX_LENGTH));
            }
        }
        $this->nameEs = $nameEs;
        $this->nameEn = $nameEn;
    }

    private function setPosition(int $position): void
    {
        if ($position < 0) {
            throw new InvalidCategory('La posición no puede ser negativa.');
        }
        $this->position = $position;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
