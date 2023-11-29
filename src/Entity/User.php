<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Client $company = null;

    #[ORM\Column(type: Types::JSON)]
    private array $permission = [];

    #[ORM\Column(length: 60)]
    private ?string $firstName = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(length: 120)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?bool $isCheckValidation = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $isCheckValidationAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $removedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\OneToMany(mappedBy: 'userHistoric', targetEntity: UserPassword::class)]
    private Collection $historicPasswords;

    public function __construct()
    {
        $this->historicPasswords = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->email;
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
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    public function getCompany(): ?Client
    {
        return $this->company;
    }

    public function setCompany(?Client $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getPermission(): array
    {
        return $this->permission;
    }

    public function setPermission(array $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function setMiddleName(?string $middleName): static
    {
        $this->middleName = $middleName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isIsCheckValidation(): ?bool
    {
        return $this->isCheckValidation;
    }

    public function setIsCheckValidation(bool $isCheckValidation): static
    {
        $this->isCheckValidation = $isCheckValidation;

        return $this;
    }

    public function getIsActiveAt(): ?DateTimeImmutable
    {
        return $this->isActiveAt;
    }

    public function setIsActiveAt(?DateTimeImmutable $isActiveAt): static
    {
        $this->isActiveAt = $isActiveAt;

        return $this;
    }

    public function getIsCheckValidationAt(): ?DateTimeImmutable
    {
        return $this->isCheckValidationAt;
    }

    public function setIsCheckValidationAt(?DateTimeImmutable $isCheckValidationAt): static
    {
        $this->isCheckValidationAt = $isCheckValidationAt;

        return $this;
    }

    public function getRemovedAt(): ?DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?DateTimeImmutable $removedAt): static
    {
        $this->removedAt = $removedAt;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return Collection<int, UserPassword>
     */
    public function getHistoricPasswords(): Collection
    {
        return $this->historicPasswords;
    }

    public function addHistoricPassword(UserPassword $historicPassword): static
    {
        if (!$this->historicPasswords->contains($historicPassword)) {
            $this->historicPasswords->add($historicPassword);
            $historicPassword->setUserHistoric($this);
        }

        return $this;
    }

    public function removeHistoricPassword(UserPassword $historicPassword): static
    {
        if ($this->historicPasswords->removeElement($historicPassword)) {
            // set the owning side to null (unless already changed)
            if ($historicPassword->getUserHistoric() === $this) {
                $historicPassword->setUserHistoric(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
