<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

use App\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vendedor (COMR-103): quien es dueño de un producto del mercado y, más adelante, fija su precio. Cuelga de una
 * unidad organizativa y se imputa a uno de sus centros de costo. La unidad se guarda por su id (es otro
 * contexto); la base lo refuerza con una clave foránea.
 *
 * Invariantes: una unidad tiene como mucho un vendedor; el slug es único e inmutable; hay exactamente un
 * vendedor de plataforma, que nunca se desactiva ni se borra (la base lo refuerza con un índice único parcial).
 * El vendedor de plataforma lo crea la migración: ningún caso de uso crea uno.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_seller')]
#[ORM\UniqueConstraint(name: 'uniq_market_seller_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_market_seller_org_unit', columns: ['org_unit_id'])]
class Seller
{
    public const NAME_MAX_LENGTH = 160;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'org_unit_id', type: 'integer')]
    private int $orgUnitId;

    #[ORM\Column(name: 'cost_center_id', type: 'integer')]
    private int $costCenterId;

    #[ORM\Column(type: 'string', length: 60)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'is_platform', type: 'boolean')]
    private bool $platform;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(int $orgUnitId, int $costCenterId, Slug $slug, string $name, bool $platform)
    {
        $this->orgUnitId = $orgUnitId;
        $this->costCenterId = $costCenterId;
        $this->slug = $slug->value();
        $this->name = self::validName($name);
        $this->platform = $platform;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(int $orgUnitId, int $costCenterId, Slug $slug, string $name): self
    {
        return new self($orgUnitId, $costCenterId, $slug, $name, false);
    }

    /** Solo para sembrar el vendedor de plataforma; los casos de uso no lo llaman. */
    public static function platform(int $orgUnitId, int $costCenterId, Slug $slug, string $name): self
    {
        return new self($orgUnitId, $costCenterId, $slug, $name, true);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function orgUnitId(): int
    {
        return $this->orgUnitId;
    }

    public function costCenterId(): int
    {
        return $this->costCenterId;
    }

    public function slug(): Slug
    {
        return new Slug($this->slug);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isPlatform(): bool
    {
        return $this->platform;
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

    public function chargeTo(int $costCenterId): void
    {
        $this->costCenterId = $costCenterId;
        $this->touch();
    }

    public function deactivate(): void
    {
        if ($this->platform) {
            throw PlatformSellerProtected::cannotBeDeactivated();
        }
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
            throw new InvalidSeller('El vendedor necesita un nombre.');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidSeller(sprintf('El nombre del vendedor admite como mucho %d caracteres.', self::NAME_MAX_LENGTH));
        }

        return $name;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
