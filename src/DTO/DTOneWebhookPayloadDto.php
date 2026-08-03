<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload del callback de DTOne (POST /api/webhooks/dtone/{token}).
 *
 * Este body NO está firmado ni autenticado por DTOne (ver
 * DTOneWebhookController) — se trata como entrada NO confiable: solo se lee
 * `externalId` (nuestro transactionId) para localizar la venta y disparar
 * un CheckSaleMessage que consulta el estado real vía la API autenticada.
 * Nunca se usa `status` de este DTO para escribir un estado directamente.
 */
final class DTOneWebhookPayloadDto implements IInput
{
    #[Assert\NotBlank]
    #[SerializedName('external_id')]
    protected ?string $externalId;

    #[SerializedName('id')]
    protected ?string $eventId;

    public function __construct(?string $externalId = null, ?string $eventId = null)
    {
        $this->externalId = $externalId;
        $this->eventId = $eventId;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(?string $eventId): void
    {
        $this->eventId = $eventId;
    }
}
