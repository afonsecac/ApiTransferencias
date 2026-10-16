<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

use Doctrine\ORM\Mapping as ORM;

/** Un hecho de la vida de una tarjeta (emitida, vendida, PIN mostrado, canjeada, anulada). Inmutable: la base rechaza cambiarlo o borrarlo. */
#[ORM\Entity]
#[ORM\Table(name: 'market_gift_card_event')]
#[ORM\Index(name: 'idx_market_gift_card_event_card', columns: ['card_id', 'created_at'])]
class GiftCardEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'card_id', type: 'integer')]
    private int $cardId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(name: 'actor_name', type: 'string', length: 180)]
    private string $actorName;

    #[ORM\Column(type: 'string', length: 300, nullable: true)]
    private ?string $detail;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(int $cardId, string $type, string $actorName, ?string $detail, \DateTimeImmutable $at)
    {
        $this->cardId = $cardId;
        $this->type = $type;
        $this->actorName = mb_substr($actorName, 0, 180);
        $this->detail = $detail === null ? null : mb_substr($detail, 0, 300);
        $this->createdAt = $at;
    }

    public static function of(int $cardId, string $type, string $actorName, ?string $detail, \DateTimeImmutable $at): self
    {
        return new self($cardId, $type, $actorName, $detail, $at);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function cardId(): int
    {
        return $this->cardId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function actorName(): string
    {
        return $this->actorName;
    }

    public function detail(): ?string
    {
        return $this->detail;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
