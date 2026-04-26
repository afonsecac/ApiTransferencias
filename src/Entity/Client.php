<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(
    fields: ["companyIdentification", "companyIdentificationType"], name: "index_company_identification"
)]
#[ORM\UniqueConstraint(
    name: "unique_company_information", fields: ["companyCountry", "companyIdentification", "companyIdentificationType"]
)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['balance:reading', 'profile', 'accounts:read', 'reports:list', 'report:read', 'client:list', 'permission:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['balance:reading', 'profile', 'accounts:read', 'reports:list', 'report:read', 'client:list', 'permission:read'])]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 3)]
    #[Groups(['balance:reading', 'profile', 'accounts:read', 'client:list'])]
    private ?string $companyCountry = null;

    #[ORM\Column(length: 12, nullable: true)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?string $companyZipCode = null;

    #[ORM\Column(length: 120)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?string $companyEmail = null;

    #[ORM\Column(length: 20)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?string $companyPhoneNumber = null;

    #[ORM\Column]
    #[Groups(['client:reading', 'accounts:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['client:reading'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:reading'])]
    private ?DateTimeImmutable $removeAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['balance:reading', 'profile', 'accounts:read', 'reports:list', 'report:read', 'client:list'])]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['balance:reading', 'accounts:read'])]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column]
    #[Groups(['client:reading', 'profile', 'accounts:read'])]
    private ?float $discountOfClient = null;

    #[ORM\Column(length: 255)]
    #[Groups(['balance:reading', 'profile', 'accounts:read', 'client:list'])]
    private ?string $companyIdentification = null;

    #[ORM\Column(length: 255)]
    #[Groups(['balance:reading', 'accounts:read'])]
    private ?string $companyIdentificationType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:reading', 'accounts:read'])]
    private ?float $minBalance = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:reading', 'accounts:read'])]
    private ?float $criticalBalance = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['balance:reading', 'accounts:read'])]
    private ?bool $isAlert = null;

    /**
     * @var Collection<int, Account>
     */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'client')]
    private Collection $accounts;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contractWith = null;

    public function __construct()
    {
        $this->isActive = false;
        $this->accounts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    public function getCompanyCountry(): ?string
    {
        return $this->companyCountry;
    }

    public function setCompanyCountry(string $companyCountry): static
    {
        $this->companyCountry = $companyCountry;

        return $this;
    }

    public function getCompanyZipCode(): ?string
    {
        return $this->companyZipCode;
    }

    public function setCompanyZipCode(?string $companyZipCode): static
    {
        $this->companyZipCode = $companyZipCode;

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

    public function getCompanyPhoneNumber(): ?string
    {
        return $this->companyPhoneNumber;
    }

    public function setCompanyPhoneNumber(string $companyPhoneNumber): static
    {
        $this->companyPhoneNumber = $companyPhoneNumber;

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

    public function getRemoveAt(): ?DateTimeImmutable
    {
        return $this->removeAt;
    }

    public function setRemoveAt(?DateTimeImmutable $removeAt): static
    {
        $this->removeAt = $removeAt;

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

    public function getIsActiveAt(): ?DateTimeImmutable
    {
        return $this->isActiveAt;
    }

    public function setIsActiveAt(?DateTimeImmutable $isActiveAt): static
    {
        $this->isActiveAt = $isActiveAt;

        return $this;
    }

    public function getDiscountOfClient(): ?float
    {
        return $this->discountOfClient;
    }

    public function setDiscountOfClient(float $discountOfClient): static
    {
        $this->discountOfClient = $discountOfClient;

        return $this;
    }

    public function getCompanyIdentification(): ?string
    {
        return $this->companyIdentification;
    }

    public function setCompanyIdentification(string $companyIdentification): static
    {
        $this->companyIdentification = $companyIdentification;

        return $this;
    }

    public function getCompanyIdentificationType(): ?string
    {
        return $this->companyIdentificationType;
    }

    public function setCompanyIdentificationType(string $companyIdentificationType): static
    {
        $this->companyIdentificationType = $companyIdentificationType;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PostRemove]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(?bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getMinBalance(): ?float
    {
        return $this->minBalance;
    }

    public function setMinBalance(?float $minBalance): static
    {
        $this->minBalance = $minBalance;

        return $this;
    }

    public function getCriticalBalance(): ?float
    {
        return $this->criticalBalance;
    }

    public function setCriticalBalance(?float $criticalBalance): static
    {
        $this->criticalBalance = $criticalBalance;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function isAlert(): ?bool
    {
        return $this->isAlert;
    }

    public function setAlert(?bool $isAlert): static
    {
        $this->isAlert = $isAlert;

        return $this;
    }

    /**
     * @return Collection
     */
    #[Groups(['profile'])]
    public function getProfileAccounts(): Collection
    {
        return $this->getAccounts()->filter(function (Account $account) {
            return $account->isActive();
        })->map(function (Account $account) {
            return $account;
        });
    }

    /**
     * @return Collection<int, Account>
     */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(Account $account): static
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
            $account->setClient($this);
        }

        return $this;
    }

    public function removeAccount(Account $account): static
    {
        if ($this->accounts->removeElement($account)) {
            // set the owning side to null (unless already changed)
            if ($account->getClient() === $this) {
                $account->setClient(null);
            }
        }

        return $this;
    }

    public function getContractWith(): ?string
    {
        return $this->contractWith;
    }

    public function setContractWith(?string $contractWith): static
    {
        $this->contractWith = $contractWith;

        return $this;
    }
}
