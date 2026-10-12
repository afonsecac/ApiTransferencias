<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: "unique_environment_by_client", fields: ["environment", "client"]
)]
class Account implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    private ?int $id = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * Interruptor de mercado de esta cuenta (ADR-001): apagado por defecto. Por sí solo no concede nada; el
     * autenticador concede ROLE_MARKET_API_USER cuando además está encendido el interruptor global
     * (App\Security\MarketAccess). Patrón reutilizable para activar una función nueva cuenta a cuenta.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $marketEnabled = false;

    /** Auditoría del último cambio del interruptor de mercado: cuándo y quién (NULL si nunca se tocó). */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $marketChangedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $marketChangedBy = null;

    /**
     * Concesión del rol de mercado para ESTA petición: la fija el autenticador y no se persiste (no está mapeada).
     */
    private bool $marketRoleGranted = false;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private ?Uuid $accessToken = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['balance:reading', 'profile', 'accounts:read'])]
    #[MaxDepth(1)]
    private ?Environment $environment = null;

    #[ORM\Column]
    #[Groups(['accounts:read'])]
    private ?float $discount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['accounts:read'])]
    private ?string $discountUnit = null;

    #[ORM\Column]
    #[Groups(['accounts:read'])]
    private ?float $commission = null;

    #[ORM\Column]
    #[Groups(['accounts:read'])]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['accounts:read'])]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    #[Groups(['accounts:read'])]
    private ?string $origin = null;

    #[ORM\Column(nullable: true)]
    private ?int $accountId = null;

    #[ORM\Column(length: 255)]
    private ?string $environmentName = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['balance:reading',  'profile', 'accounts:read'])]
    private ?string $contractCurrency = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['accounts:read'])]
    private ?float $minBalance = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['account:read'])]
    private ?float $criticalBalance = null;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['balance:reading', 'accounts:read'])]
    #[MaxDepth(1)]
    private ?Client $client = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['profile', 'accounts:read'])]
    private ?bool $isPreferAdmin = null;

    public function __construct()
    {
        $this->discount = 0;
        $this->discountUnit = '%';
        $this->commission = 0;
        $this->contractCurrency = 'EUR';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->accessToken;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_API_USER';
        if ($this->marketRoleGranted) {
            $roles[] = 'ROLE_MARKET_API_USER';
        }

        return array_unique($roles);
    }

    public function isMarketEnabled(): bool
    {
        return $this->marketEnabled;
    }

    public function setMarketEnabled(bool $marketEnabled): static
    {
        $this->marketEnabled = $marketEnabled;

        return $this;
    }

    public function getMarketChangedAt(): ?DateTimeImmutable
    {
        return $this->marketChangedAt;
    }

    public function getMarketChangedBy(): ?User
    {
        return $this->marketChangedBy;
    }

    /**
     * Registra el cambio del interruptor de mercado: cuándo y quién.
     */
    public function markMarketChanged(?User $by, DateTimeImmutable $at): static
    {
        $this->marketChangedBy = $by;
        $this->marketChangedAt = $at;

        return $this;
    }

    /**
     * Lo llama ApiTokenAuthenticator, tras comprobar con MarketAccess que la cuenta y el interruptor global lo
     * permiten. No se persiste: vale solo para esta petición.
     */
    public function grantMarketRole(): static
    {
        $this->marketRoleGranted = true;

        return $this;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getAccessToken(): ?Uuid
    {
        return $this->accessToken;
    }

    public function setAccessToken(Uuid $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getDiscountUnit(): ?string
    {
        return $this->discountUnit;
    }

    public function setDiscountUnit(string $discountUnit): static
    {
        $this->discountUnit = $discountUnit;

        return $this;
    }

    public function getCommission(): ?float
    {
        return $this->commission;
    }

    public function setCommission(float $commission): static
    {
        $this->commission = $commission;

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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->accessToken = Uuid::v7();
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function setAccountId(?int $accountId): static
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function getEnvironmentName(): ?string
    {
        return $this->environmentName;
    }

    public function setEnvironmentName(string $environmentName): static
    {
        $this->environmentName = $environmentName;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getContractCurrency(): ?string
    {
        return $this->contractCurrency;
    }

    public function setContractCurrency(?string $contractCurrency): static
    {
        $this->contractCurrency = $contractCurrency;

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function isPreferAdmin(): ?bool
    {
        return $this->isPreferAdmin;
    }

    public function setPreferAdmin(?bool $isPreferAdmin): static
    {
        $this->isPreferAdmin = $isPreferAdmin;

        return $this;
    }
}
