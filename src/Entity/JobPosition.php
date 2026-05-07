<?php

namespace App\Entity;

use App\Enums\JobPositionAreaEnum;
use App\Repository\JobPositionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: JobPositionRepository::class)]
#[ORM\Table(name: 'job_position')]
#[ORM\HasLifecycleCallbacks]
class JobPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['job_position:list', 'job_position:detail', 'user:job_position'])]
    private ?int $id = null;

    #[ORM\Column(length: 3, unique: true)]
    #[Groups(['job_position:list', 'job_position:detail', 'user:job_position'])]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Groups(['job_position:list', 'job_position:detail', 'user:job_position'])]
    private ?string $name = null;

    #[ORM\Column(length: 20, enumType: JobPositionAreaEnum::class)]
    #[Groups(['job_position:list', 'job_position:detail', 'user:job_position'])]
    private ?JobPositionAreaEnum $area = null;

    #[ORM\Column]
    #[Groups(['job_position:list', 'job_position:detail'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['job_position:detail'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['job_position:detail'])]
    private ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
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

    public function getArea(): ?JobPositionAreaEnum
    {
        return $this->area;
    }

    public function setArea(JobPositionAreaEnum $area): static
    {
        $this->area = $area;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtNow(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdatedAtNow(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
