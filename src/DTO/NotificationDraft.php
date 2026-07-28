<?php

namespace App\DTO;

use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;

/**
 * Value object interno para construir una notificación antes de persistirla.
 * No implementa IInput: nunca llega directamente de una petición HTTP, lo
 * construyen los servicios/handlers que detectan un evento de dominio (o el
 * controlador de difusión manual, a partir de un CreateNotificationDto ya
 * validado).
 */
final class NotificationDraft
{
    public function __construct(
        public readonly NotificationTypeEnum $type,
        public readonly string $title,
        public readonly NotificationLevelEnum $level = NotificationLevelEnum::INFO,
        public readonly ?string $body = null,
        public readonly ?string $link = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $data = null,
        public readonly ?int $environmentId = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }
}
