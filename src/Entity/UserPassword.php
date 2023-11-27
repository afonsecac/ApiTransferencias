<?php

namespace App\Entity;

use App\Repository\UserPasswordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserPasswordRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserPassword
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(inversedBy: 'historicPasswords')]
    private ?User $userHistoric = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $historicPassword = null;

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getUserHistoric(): ?User
    {
        return $this->userHistoric;
    }

    public function setUserHistoric(?User $userHistoric): static
    {
        $this->userHistoric = $userHistoric;

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

    public function getHistoricPassword(): ?string
    {
        return $this->historicPassword;
    }

    public function setHistoricPassword(string $historicPassword): static
    {
        $this->historicPassword = $historicPassword;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->historicPassword = $this->getUserHistoric()?->getPassword() ?? '';
    }
}
