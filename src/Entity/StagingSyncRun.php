<?php

namespace App\Entity;

use App\Enums\StagingSyncStatusEnum;
use App\Repository\StagingSyncRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una corrida de scripts/sync-prod-to-staging.sh (copia la BD de prod hacia
 * staging). La escribe App\Service\StagingSyncService::report(), llamado por
 * el propio script vía `bin/console app:staging-sync:report` en cada
 * checkpoint (inicio/éxito/fallo) — tanto si lo disparó el cron mensual como
 * un admin desde el dashboard.
 */
#[ORM\Entity(repositoryClass: StagingSyncRunRepository::class)]
class StagingSyncRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: StagingSyncStatusEnum::class)]
    private ?StagingSyncStatusEnum $status = null;

    /** Email del admin que lo disparó desde el dashboard, o 'cron' si fue el mensual. */
    #[ORM\Column(name: 'triggered_by', length: 255, nullable: true)]
    private ?string $triggeredBy = null;

    #[ORM\Column(name: 'started_at')]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?StagingSyncStatusEnum
    {
        return $this->status;
    }

    public function setStatus(StagingSyncStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTriggeredBy(): ?string
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?string $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
