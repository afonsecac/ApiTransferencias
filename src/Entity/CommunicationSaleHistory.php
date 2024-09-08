<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CommunicationSaleHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CommunicationSaleHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historical')]
    private ?CommunicationSaleInfo $sale = null;

    #[ORM\Column(length: 20)]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    private ?CommunicationStateEnum $state = null;

    #[ORM\Column]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    private array $info = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[ApiProperty]
    #[Groups(['comSales:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): ?CommunicationSaleInfo
    {
        return $this->sale;
    }

    public function setSale(?CommunicationSaleInfo $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getState(): ?CommunicationStateEnum
    {
        return $this->state;
    }

    public function setState(CommunicationStateEnum $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function setInfo(array $info): static
    {
        $this->info = $info;

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setUpdated(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
