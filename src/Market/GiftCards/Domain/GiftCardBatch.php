<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

use Doctrine\ORM\Mapping as ORM;

/** Un lote de emisión: cuántas tarjetas, de qué producto, con qué caducidad y a qué centro de costo, y quién lo emitió. */
#[ORM\Entity]
#[ORM\Table(name: 'market_gift_card_batch')]
class GiftCardBatch
{
    public const MAX_QUANTITY = 1000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'product_id', type: 'integer')]
    private int $productId;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'expires_at', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'cost_center_id', type: 'integer')]
    private int $costCenterId;

    #[ORM\Column(name: 'issued_by', type: 'string', length: 180)]
    private string $issuedBy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(int $productId, int $quantity, ?\DateTimeImmutable $expiresAt, int $costCenterId, string $issuedBy, \DateTimeImmutable $at)
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidGiftCard(sprintf('Un lote lleva entre 1 y %d tarjetas.', self::MAX_QUANTITY));
        }
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->expiresAt = $expiresAt;
        $this->costCenterId = $costCenterId;
        $this->issuedBy = mb_substr($issuedBy, 0, 180);
        $this->createdAt = $at;
    }

    public static function open(int $productId, int $quantity, ?\DateTimeImmutable $expiresAt, int $costCenterId, string $issuedBy, \DateTimeImmutable $at): self
    {
        return new self($productId, $quantity, $expiresAt, $costCenterId, $issuedBy, $at);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /** El código legible del lote: L-año-mes-número. */
    public function code(): string
    {
        return sprintf('L-%s-%04d', $this->createdAt->format('Y-m'), (int) $this->id);
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function costCenterId(): int
    {
        return $this->costCenterId;
    }

    public function issuedBy(): string
    {
        return $this->issuedBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
