<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Difusión manual de una notificación desde el dashboard (ROLE_ADMIN). Es
 * también el único mecanismo para sembrar notificaciones deterministas desde
 * los e2e, ya que las demás nacen de eventos de dominio que no se pueden
 * disparar a voluntad.
 */
class CreateNotificationDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['USER', 'ROLE', 'CLIENT', 'GLOBAL'])]
    protected ?string $audience;

    #[Assert\Choice(choices: ['INFO', 'SUCCESS', 'WARNING', 'ERROR', 'CRITICAL'])]
    protected ?string $level = 'INFO';

    #[Assert\Positive]
    protected ?int $targetUserId;

    #[Assert\Length(max: 50)]
    protected ?string $targetRole;

    #[Assert\Positive]
    protected ?int $clientId;

    #[Assert\Positive]
    protected ?int $environmentId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    protected ?string $title;

    protected ?string $body;

    #[Assert\Length(max: 255)]
    protected ?string $link;

    /** @var array<string, mixed>|null */
    protected ?array $data;

    public function __construct(
        ?string $audience = null,
        ?string $level = 'INFO',
        ?int $targetUserId = null,
        ?string $targetRole = null,
        ?int $clientId = null,
        ?int $environmentId = null,
        ?string $title = null,
        ?string $body = null,
        ?string $link = null,
        ?array $data = null,
    ) {
        $this->audience = $audience;
        $this->level = $level;
        $this->targetUserId = $targetUserId;
        $this->targetRole = $targetRole;
        $this->clientId = $clientId;
        $this->environmentId = $environmentId;
        $this->title = $title;
        $this->body = $body;
        $this->link = $link;
        $this->data = $data;
    }

    public function getAudience(): ?string
    {
        return $this->audience;
    }

    public function setAudience(?string $audience): void
    {
        $this->audience = $audience;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): void
    {
        $this->level = $level;
    }

    public function getTargetUserId(): ?int
    {
        return $this->targetUserId;
    }

    public function setTargetUserId(?int $targetUserId): void
    {
        $this->targetUserId = $targetUserId;
    }

    public function getTargetRole(): ?string
    {
        return $this->targetRole;
    }

    public function setTargetRole(?string $targetRole): void
    {
        $this->targetRole = $targetRole;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getEnvironmentId(): ?int
    {
        return $this->environmentId;
    }

    public function setEnvironmentId(?int $environmentId): void
    {
        $this->environmentId = $environmentId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
