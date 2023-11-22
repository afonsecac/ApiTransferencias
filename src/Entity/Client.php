<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $uuid = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $companyEmail = null;

    #[ORM\Column(length: 20)]
    private ?string $companyPhone = null;

    #[ORM\Column(length: 20)]
    private ?string $companyTel = null;

    #[ORM\Column(length: 100)]
    private ?string $companySite = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $companyDescription = null;

    #[ORM\Column(length: 255)]
    private ?string $companyLegalRepresentative = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inactiveAt = null;

    #[ORM\Column]
    private ?bool $isAcceptedPolitics = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column]
    private ?bool $isAcceptedOffer = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $acceptedOfferAt = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $origins = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->companyEmail;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(string $companyEmail): static
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(string $companyPhone): static
    {
        $this->companyPhone = $companyPhone;

        return $this;
    }

    public function getCompanyTel(): ?string
    {
        return $this->companyTel;
    }

    public function setCompanyTel(string $companyTel): static
    {
        $this->companyTel = $companyTel;

        return $this;
    }

    public function getCompanySite(): ?string
    {
        return $this->companySite;
    }

    public function setCompanySite(string $companySite): static
    {
        $this->companySite = $companySite;

        return $this;
    }

    public function getCompanyDescription(): ?string
    {
        return $this->companyDescription;
    }

    public function setCompanyDescription(string $companyDescription): static
    {
        $this->companyDescription = $companyDescription;

        return $this;
    }

    public function getCompanyLegalRepresentative(): ?string
    {
        return $this->companyLegalRepresentative;
    }

    public function setCompanyLegalRepresentative(string $companyLegalRepresentative): static
    {
        $this->companyLegalRepresentative = $companyLegalRepresentative;

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

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getInactiveAt(): ?\DateTimeImmutable
    {
        return $this->inactiveAt;
    }

    public function setInactiveAt(?\DateTimeImmutable $inactiveAt): static
    {
        $this->inactiveAt = $inactiveAt;

        return $this;
    }

    public function isIsAceptedPolitics(): ?bool
    {
        return $this->isAcceptedPolitics;
    }

    public function setIsAcceptedPolitics(bool $isAcceptedPolitics): static
    {
        $this->isAcceptedPolitics = $isAcceptedPolitics;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function isIsAceptedOffer(): ?bool
    {
        return $this->isAcceptedOffer;
    }

    public function setIsAcceptedOffer(bool $isAcceptedOffer): static
    {
        $this->isAcceptedOffer = $isAcceptedOffer;

        return $this;
    }

    public function getAcceptedOfferAt(): ?\DateTimeImmutable
    {
        return $this->acceptedOfferAt;
    }

    public function setAcceptedOfferAt(\DateTimeImmutable $acceptedOfferAt): static
    {
        $this->acceptedOfferAt = $acceptedOfferAt;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getOrigins(): ?string
    {
        return $this->origins;
    }

    public function setOrigins(string $origins): static
    {
        $this->origins = $origins;

        return $this;
    }
}
