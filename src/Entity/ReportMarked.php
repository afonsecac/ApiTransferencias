<?php

namespace App\Entity;

use App\Repository\ReportMarkedRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ReportMarkedRepository::class)]
class ReportMarked
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reports:list', 'report:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['reports:list', 'report:read'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['reports:list', 'report:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['report:read'])]
    private ?int $lastOperationMarked = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reports:list', 'report:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    #[Groups(['report:read'])]
    private ?array $dataArray = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getLastOperationMarked(): ?int
    {
        return $this->lastOperationMarked;
    }

    public function setLastOperationMarked(int $lastOperationMarked): static
    {
        $this->lastOperationMarked = $lastOperationMarked;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getDataArray(): ?array
    {
        return $this->dataArray;
    }

    public function setDataArray(?array $dataArray): static
    {
        $this->dataArray = $dataArray;

        return $this;
    }
}
