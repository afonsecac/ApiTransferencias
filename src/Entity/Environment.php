<?php

namespace App\Entity;

use App\Repository\EnvironmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnvironmentRepository::class)]
#[ORM\UniqueConstraint(
    name: "uniqueTxEnvironment",
    fields: ["environment", "urlPath"]
)]
#[ORM\Index(
    fields: ["environment"],
    name: "indexTxEnvironment"
)]
#[ORM\Index(
    fields: ["urlPath"],
    name: "indexTxEnvironmentUrlPath"
)]
#[ORM\HasLifecycleCallbacks()]
class Environment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $environment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $urlPath = null;

    #[ORM\Column(length: 255)]
    private ?string $apiKey = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?\DateTimeImmutable $removedAt): static
    {
        $this->removedAt = $removedAt;

        return $this;
    }

    public function getUrlPath(): ?string
    {
        return $this->urlPath;
    }

    public function setUrlPath(string $urlPath): static
    {
        $this->urlPath = $urlPath;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    #[ORM\PreRemove]
    public function setRemoved(): void {
        $this->removedAt = new \DateTimeImmutable('now');
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }
}
