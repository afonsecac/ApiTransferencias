<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

use App\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;

/**
 * Almacén (COMR-285): un lugar con dueño (una unidad organizativa de la plataforma o de un socio de almacén) y un municipio.
 * Estados: borrador → activo ⇄ inactivo; nunca vuelve a borrador. Solo un almacén activo vende. El principal de la plataforma
 * lo siembra la migración: nace activo, no se desactiva, no se descarta y no cambia de dueño. Su identificador no cambia.
 *
 * Propietario de la mercancía = vendedor del producto; custodio = dueño del almacén. Lo uno y lo otro llega con el stock.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_warehouse')]
#[ORM\UniqueConstraint(name: 'uniq_market_warehouse_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_market_warehouse_org_unit', columns: ['org_unit_id'])]
class Warehouse
{
    public const NAME_MAX_LENGTH = 160;
    public const ADDRESS_MAX_LENGTH = 300;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 60)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 10, enumType: WarehouseStatus::class)]
    private WarehouseStatus $status;

    #[ORM\Column(name: 'is_main', type: 'boolean')]
    private bool $main;

    #[ORM\Column(name: 'org_unit_id', type: 'integer', nullable: true)]
    private ?int $orgUnitId = null;

    #[ORM\Column(name: 'cost_center_id', type: 'integer', nullable: true)]
    private ?int $costCenterId = null;

    #[ORM\Column(name: 'municipality_id', type: 'integer', nullable: true)]
    private ?int $municipalityId = null;

    #[ORM\Column(name: 'perm_sets_coverage', type: 'boolean')]
    private bool $setsCoverage = false;

    #[ORM\Column(name: 'perm_assembles_combos', type: 'boolean')]
    private bool $assemblesCombos = false;

    #[ORM\Column(name: 'perm_sees_supply_cost', type: 'boolean')]
    private bool $seesSupplyCost = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(Slug $slug, string $name, WarehouseStatus $status, bool $main)
    {
        $this->slug = $slug->value();
        $this->name = self::validName($name);
        $this->status = $status;
        $this->main = $main;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /** Un almacén nuevo: borrador con solo nombre e identificador. */
    public static function draft(Slug $slug, string $name): self
    {
        return new self($slug, $name, WarehouseStatus::Draft, false);
    }

    /** Solo para sembrar el almacén principal; los casos de uso no lo llaman. */
    public static function main(Slug $slug, string $name, int $orgUnitId, int $costCenterId, int $municipalityId): self
    {
        $warehouse = new self($slug, $name, WarehouseStatus::Active, true);
        $warehouse->orgUnitId = $orgUnitId;
        $warehouse->costCenterId = $costCenterId;
        $warehouse->municipalityId = $municipalityId;

        return $warehouse;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function slug(): Slug
    {
        return new Slug($this->slug);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function status(): WarehouseStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === WarehouseStatus::Active;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    public function orgUnitId(): ?int
    {
        return $this->orgUnitId;
    }

    public function costCenterId(): ?int
    {
        return $this->costCenterId;
    }

    public function municipalityId(): ?int
    {
        return $this->municipalityId;
    }

    public function permissions(): PartnerPermissions
    {
        return new PartnerPermissions($this->setsCoverage, $this->assemblesCombos, $this->seesSupplyCost);
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

    public function changeAddress(?string $address): void
    {
        $address = $address === null ? null : trim($address);
        if ($address !== null && mb_strlen($address) > self::ADDRESS_MAX_LENGTH) {
            throw new InvalidWarehouse(sprintf('La dirección admite como mucho %d caracteres.', self::ADDRESS_MAX_LENGTH));
        }
        $this->address = $address === '' ? null : $address;
        $this->touch();
    }

    public function locate(?int $municipalityId): void
    {
        $this->municipalityId = $municipalityId;
        $this->touch();
    }

    /** Cambiar de dueño sin indicar centro de costo deja el centro vacío: el nuevo dueño tiene los suyos. */
    public function assignOwner(?int $orgUnitId, ?int $costCenterId): void
    {
        if ($this->main && $orgUnitId !== $this->orgUnitId) {
            throw MainWarehouseProtected::cannotChangeOwner();
        }
        $this->orgUnitId = $orgUnitId;
        $this->costCenterId = $orgUnitId === null ? null : $costCenterId;
        $this->touch();
    }

    public function grant(PartnerPermissions $permissions): void
    {
        $this->setsCoverage = $permissions->setsCoverage;
        $this->assemblesCombos = $permissions->assemblesCombos;
        $this->seesSupplyCost = $permissions->seesSupplyCost;
        $this->touch();
    }

    /** @return list<string> lo que falta para poder activarlo */
    public function missingForActivation(): array
    {
        return array_values(array_filter([
            $this->orgUnitId === null ? 'owner' : null,
            $this->costCenterId === null ? 'cost_center' : null,
            $this->municipalityId === null ? 'municipality' : null,
        ]));
    }

    /**
     * De borrador o de inactivo a activo.
     *
     * @throws WarehouseIncomplete
     * @throws WarehouseTransitionInvalid
     */
    public function activate(): void
    {
        if ($this->status === WarehouseStatus::Active) {
            throw WarehouseTransitionInvalid::between($this->status, 'activar');
        }
        if (($missing = $this->missingForActivation()) !== []) {
            throw new WarehouseIncomplete($missing);
        }
        $this->status = WarehouseStatus::Active;
        $this->touch();
    }

    /**
     * Solo un activo. Deja de vender, pero lo ya vendido se procesa (lo aplican cobertura, stock y pedidos).
     *
     * @throws MainWarehouseProtected
     * @throws WarehouseTransitionInvalid
     */
    public function deactivate(): void
    {
        if ($this->main) {
            throw MainWarehouseProtected::cannotBeDeactivated();
        }
        if ($this->status !== WarehouseStatus::Active) {
            throw WarehouseTransitionInvalid::between($this->status, 'desactivar');
        }
        $this->status = WarehouseStatus::Inactive;
        $this->touch();
    }

    /** Solo un borrador (nunca fue activo) se puede descartar. */
    public function canBeDiscarded(): bool
    {
        return !$this->main && $this->status === WarehouseStatus::Draft;
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidWarehouse('El almacén necesita un nombre.');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidWarehouse(sprintf('El nombre del almacén admite como mucho %d caracteres.', self::NAME_MAX_LENGTH));
        }

        return $name;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
