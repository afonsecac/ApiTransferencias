<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un movimiento de stock (COMR-194): inmutable (la base rechaza modificarlo o borrarlo). Lleva la cantidad con signo, lo que
 * quedó en mano, el motivo y el comentario, la referencia opcional, quién lo hizo y los dos centros de costo: el del propietario
 * (el vendedor del producto, al que va el valor) y el del custodio (el dueño del almacén, al que van custodia y manipulación).
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_stock_movement')]
#[ORM\Index(name: 'idx_market_stock_movement_warehouse', columns: ['warehouse_id', 'created_at'])]
#[ORM\Index(name: 'idx_market_stock_movement_item', columns: ['item_type', 'item_id', 'created_at'])]
class StockMovement
{
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const COMMENT_MIN = 3;
    public const COMMENT_MAX = 500;
    public const REFERENCE_MAX = 80;

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

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'on_hand_after', type: 'integer')]
    private int $onHandAfter;

    #[ORM\Column(type: 'string', length: 20, enumType: StockReason::class)]
    private StockReason $reason;

    #[ORM\Column(type: 'string', length: 500)]
    private string $comment;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $reference;

    #[ORM\Column(name: 'actor_id', type: 'integer', nullable: true)]
    private ?int $actorId;

    #[ORM\Column(name: 'actor_name', type: 'string', length: 180)]
    private string $actorName;

    #[ORM\Column(name: 'owner_cost_center_id', type: 'integer')]
    private int $ownerCostCenterId;

    #[ORM\Column(name: 'custodian_cost_center_id', type: 'integer')]
    private int $custodianCostCenterId;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    /**
     * Registra un ajuste que ya se aplicó a la línea (lo en mano de la línea es lo que queda).
     *
     * @throws InvalidStock sin comentario válido
     */
    public static function adjustment(StockLine $line, int $delta, StockReason $reason, string $comment, ?string $reference, StockActor $actor, int $ownerCostCenterId, int $custodianCostCenterId): self
    {
        $comment = trim($comment);
        if (mb_strlen($comment) < self::COMMENT_MIN || mb_strlen($comment) > self::COMMENT_MAX) {
            throw new InvalidStock(sprintf('El comentario es obligatorio: entre %d y %d caracteres.', self::COMMENT_MIN, self::COMMENT_MAX));
        }
        $reference = $reference === null ? null : trim($reference);
        if ($reference !== null && mb_strlen($reference) > self::REFERENCE_MAX) {
            throw new InvalidStock(sprintf('La referencia admite como mucho %d caracteres.', self::REFERENCE_MAX));
        }

        $movement = new self();
        $movement->warehouseId = $line->warehouseId();
        $movement->itemType = $line->itemType();
        $movement->itemId = $line->itemId();
        $movement->type = self::TYPE_ADJUSTMENT;
        $movement->quantity = $delta;
        $movement->onHandAfter = $line->onHand();
        $movement->reason = $reason;
        $movement->comment = $comment;
        $movement->reference = $reference === '' ? null : $reference;
        $movement->actorId = $actor->id;
        $movement->actorName = mb_substr($actor->name, 0, 180);
        $movement->ownerCostCenterId = $ownerCostCenterId;
        $movement->custodianCostCenterId = $custodianCostCenterId;
        $movement->createdAt = new \DateTimeImmutable();

        return $movement;
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

    public function type(): string
    {
        return $this->type;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function onHandAfter(): int
    {
        return $this->onHandAfter;
    }

    public function reason(): StockReason
    {
        return $this->reason;
    }

    public function comment(): string
    {
        return $this->comment;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function actorId(): ?int
    {
        return $this->actorId;
    }

    public function actorName(): string
    {
        return $this->actorName;
    }

    public function ownerCostCenterId(): int
    {
        return $this->ownerCostCenterId;
    }

    public function custodianCostCenterId(): int
    {
        return $this->custodianCostCenterId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
