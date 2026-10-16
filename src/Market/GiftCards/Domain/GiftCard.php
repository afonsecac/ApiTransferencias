<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Gift card propia (COMR-276): una tarjeta digital con serie y PIN, emitida en un lote, que se activa al venderse y se canjea una
 * sola vez. El PIN se guarda cifrado (para mostrarlo con registro) y como huella (para buscar al canjear); nunca en claro.
 *
 * Estados: emitida → activa (vendida) → canjeada; anulada desde emitida o activa; «vencida» se deduce de la caducidad. Canjear
 * dos veces con la misma clave es una repetición inofensiva; con otra clave, un error (idempotencia).
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_gift_card')]
#[ORM\UniqueConstraint(name: 'uniq_market_gift_card_serial', columns: ['serial'])]
#[ORM\UniqueConstraint(name: 'uniq_market_gift_card_pin_hash', columns: ['pin_hash'])]
#[ORM\Index(name: 'idx_market_gift_card_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_market_gift_card_product', columns: ['product_id'])]
class GiftCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'batch_id', type: 'integer')]
    private int $batchId;

    #[ORM\Column(name: 'product_id', type: 'integer')]
    private int $productId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $serial;

    #[ORM\Column(name: 'value_minor', type: 'bigint')]
    private int $valueMinor;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'pin_cipher', type: 'text')]
    private string $pinCipher;

    #[ORM\Column(name: 'pin_hash', type: 'string', length: 128)]
    private string $pinHash;

    #[ORM\Column(type: 'string', length: 10, enumType: GiftCardStatus::class)]
    private GiftCardStatus $status = GiftCardStatus::Issued;

    #[ORM\Column(name: 'expires_at', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'cost_center_id', type: 'integer')]
    private int $costCenterId;

    #[ORM\Column(name: 'sold_to', type: 'string', length: 180, nullable: true)]
    private ?string $soldTo = null;

    #[ORM\Column(name: 'sale_ref', type: 'string', length: 80, nullable: true)]
    private ?string $saleRef = null;

    #[ORM\Column(name: 'redeemed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $redeemedAt = null;

    #[ORM\Column(name: 'redemption_ref', type: 'string', length: 80, nullable: true)]
    private ?string $redemptionRef = null;

    #[ORM\Column(name: 'cancel_reason', type: 'string', length: 300, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function issue(int $batchId, int $productId, string $serial, int $valueMinor, string $currency, string $pinCipher, string $pinHash, ?\DateTimeImmutable $expiresAt, int $costCenterId, \DateTimeImmutable $now): self
    {
        if (preg_match('/^GC-\d{6,}$/', $serial) !== 1) {
            throw new InvalidGiftCard(sprintf('La serie "%s" no es válida: GC- y al menos seis dígitos.', $serial));
        }
        if ($valueMinor <= 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidGiftCard('Una tarjeta necesita un valor positivo y su moneda.');
        }
        $card = new self();
        $card->batchId = $batchId;
        $card->productId = $productId;
        $card->serial = $serial;
        $card->valueMinor = $valueMinor;
        $card->currency = $currency;
        $card->pinCipher = $pinCipher;
        $card->pinHash = $pinHash;
        $card->expiresAt = $expiresAt;
        $card->costCenterId = $costCenterId;
        $card->createdAt = $now;

        return $card;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function batchId(): int
    {
        return $this->batchId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function serial(): string
    {
        return $this->serial;
    }

    public function valueMinor(): int
    {
        return $this->valueMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function pinCipher(): string
    {
        return $this->pinCipher;
    }

    public function pinHash(): string
    {
        return $this->pinHash;
    }

    public function status(): GiftCardStatus
    {
        return $this->status;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function costCenterId(): int
    {
        return $this->costCenterId;
    }

    public function soldTo(): ?string
    {
        return $this->soldTo;
    }

    public function saleRef(): ?string
    {
        return $this->saleRef;
    }

    public function redeemedAt(): ?\DateTimeImmutable
    {
        return $this->redeemedAt;
    }

    public function redemptionRef(): ?string
    {
        return $this->redemptionRef;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** El estado que se muestra: «vencida» si pasó la caducidad (el último día aún vale) y no está canjeada ni anulada. */
    public function effectiveStatus(\DateTimeImmutable $now): GiftCardStatus
    {
        if (in_array($this->status, [GiftCardStatus::Issued, GiftCardStatus::Active], true) && $this->isPastExpiry($now)) {
            return GiftCardStatus::Expired;
        }

        return $this->status;
    }

    /** La venta activa la tarjeta y deja a quién y con qué venta. */
    public function markSold(string $soldTo, string $saleRef, \DateTimeImmutable $now): void
    {
        $this->assertStatus(GiftCardStatus::Issued, $now, 'vender');
        $this->status = GiftCardStatus::Active;
        $this->soldTo = mb_substr(trim($soldTo), 0, 180);
        $this->saleRef = mb_substr(trim($saleRef), 0, 80);
    }

    /**
     * Canjea la tarjeta. Devuelve verdadero la primera vez y falso si es una repetición de la misma clave de canje.
     *
     * @throws GiftCardAlreadyRedeemed con otra clave de canje
     * @throws GiftCardTransitionInvalid si no está activa o venció
     */
    public function redeem(string $redemptionRef, \DateTimeImmutable $now): bool
    {
        if ($this->status === GiftCardStatus::Redeemed) {
            if ($this->redemptionRef === $redemptionRef) {
                return false;
            }
            throw GiftCardAlreadyRedeemed::serial($this->serial);
        }
        $this->assertStatus(GiftCardStatus::Active, $now, 'canjear');
        $this->status = GiftCardStatus::Redeemed;
        $this->redeemedAt = $now;
        $this->redemptionRef = $redemptionRef;

        return true;
    }

    /** Anular, con motivo, desde emitida o activa; una canjeada no se anula. */
    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidGiftCard('Anular una tarjeta exige un motivo.');
        }
        if (!in_array($this->status, [GiftCardStatus::Issued, GiftCardStatus::Active], true)) {
            throw GiftCardTransitionInvalid::between($this->serial, $this->effectiveStatus($now), 'anular');
        }
        $this->status = GiftCardStatus::Cancelled;
        $this->cancelReason = mb_substr($reason, 0, 300);
    }

    private function assertStatus(GiftCardStatus $expected, \DateTimeImmutable $now, string $action): void
    {
        if ($this->effectiveStatus($now) !== $expected) {
            throw GiftCardTransitionInvalid::between($this->serial, $this->effectiveStatus($now), $action);
        }
    }

    private function isPastExpiry(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $now->format('Y-m-d') > $this->expiresAt->format('Y-m-d');
    }
}
