<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Una línea de existencias (COMR-193/194): (almacén, producto o combo) con lo que hay en mano, lo reservado y el stock de
 * seguridad. El propietario de la mercancía es el vendedor del producto y el custodio es el dueño del almacén. Lo en mano nunca
 * baja de lo reservado. Disponible = en mano − reservado − seguridad, y solo mientras el almacén está activo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_stock_line')]
#[ORM\UniqueConstraint(name: 'uniq_market_stock_line', columns: ['warehouse_id', 'item_type', 'item_id'])]
#[ORM\Index(name: 'idx_market_stock_line_item', columns: ['item_type', 'item_id'])]
class StockLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'warehouse_id', type: 'integer')]
    private int $warehouseId;

    #[ORM\Column(name: 'item_type', type: 'string', length: 10, enumType: StockItemType::class)]
    private StockItemType $itemType;

    #[ORM\Column(name: 'item_id', type: 'integer')]
    private int $itemId;

    #[ORM\Column(name: 'on_hand', type: 'integer')]
    private int $onHand = 0;

    #[ORM\Column(type: 'integer')]
    private int $reserved = 0;

    #[ORM\Column(type: 'integer')]
    private int $safety = 0;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(int $warehouseId, StockItemType $itemType, int $itemId)
    {
        if ($warehouseId <= 0 || $itemId <= 0) {
            throw new InvalidStock('Una línea de stock necesita almacén y artículo.');
        }
        $this->warehouseId = $warehouseId;
        $this->itemType = $itemType;
        $this->itemId = $itemId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function open(int $warehouseId, StockItemType $itemType, int $itemId): self
    {
        return new self($warehouseId, $itemType, $itemId);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function itemType(): StockItemType
    {
        return $this->itemType;
    }

    public function itemId(): int
    {
        return $this->itemId;
    }

    public function onHand(): int
    {
        return $this->onHand;
    }

    public function reserved(): int
    {
        return $this->reserved;
    }

    public function safety(): int
    {
        return $this->safety;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Suma o resta existencias y devuelve lo que queda en mano.
     *
     * @throws InvalidStock si el ajuste es 0
     * @throws StockBelowReserved si dejaría lo en mano por debajo de lo reservado
     */
    public function adjust(int $delta): int
    {
        if ($delta === 0) {
            throw new InvalidStock('Un ajuste de 0 no cambia nada.');
        }
        if ($this->onHand + $delta < $this->reserved) {
            throw StockBelowReserved::of($this->onHand, $delta, $this->reserved);
        }
        $this->onHand += $delta;
        $this->touch();

        return $this->onHand;
    }

    public function setSafety(int $safety): void
    {
        if ($safety < 0) {
            throw new InvalidStock('El stock de seguridad no puede ser negativo.');
        }
        $this->safety = $safety;
        $this->touch();
    }

    /** Reserva existencias libres (lo en mano que no está ya reservado). */
    public function reserve(int $quantity): void
    {
        if ($quantity <= 0 || $this->reserved + $quantity > $this->onHand) {
            throw new InvalidStock('No hay existencias libres para reservar esa cantidad.');
        }
        $this->reserved += $quantity;
        $this->touch();
    }

    public function release(int $quantity): void
    {
        if ($quantity <= 0 || $quantity > $this->reserved) {
            throw new InvalidStock('No hay tanta cantidad reservada para liberar.');
        }
        $this->reserved -= $quantity;
        $this->touch();
    }

    /** Lo que se puede vender: solo mientras el almacén está activo; un almacén inactivo conserva sus reservas pero no vende. */
    public function available(bool $warehouseActive): int
    {
        return $warehouseActive ? max(0, $this->onHand - $this->reserved - $this->safety) : 0;
    }

    /**
     * En alerta: lo disponible (en mano − reservado − seguridad) no supera el stock de seguridad; sin existencias también cuenta.
     * Como en el tablero: 16 en mano, 0 reservado y 10 de seguridad dejan 6 disponibles, en alerta.
     */
    public function isLow(): bool
    {
        return max(0, $this->onHand - $this->reserved - $this->safety) <= $this->safety;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
