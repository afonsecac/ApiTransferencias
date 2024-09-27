<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationPromotionsRepository;
use App\State\CommunicationPromotionProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationPromotionsRepository::class)]
#[ApiResource(
    uriTemplate: '/communication/promotions',
    operations: [
        new GetCollection(
            uriTemplate: '/communication/promotions',
            order: ['id' => 'DESC'],
            provider: CommunicationPromotionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['comProm:read']],
    denormalizationContext: ['groups' => ['comProm:create', 'comProm:update']],
    security: "is_granted('ROLE_COM_API_USER')"
)]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
], arguments: ['orderParameterName' => 'orderBy'])]
#[Orm\HasLifecycleCallbacks]
class CommunicationPromotions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comProm:read', 'comPackage:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comProm:read', 'comPackage:read'])]
    #[ApiProperty()]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comProm:read', 'comPackage:read'])]
    #[ApiProperty]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['comProm:read', 'comPackage:read'])]
    #[ApiProperty]
    private ?string $infoDescription = null;

    #[ORM\Column]
    #[ApiProperty]
    private array $terms = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    #[Groups(['comProm:read', 'comPackage:read'])]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    #[Groups(['comProm:read', 'comPackage:read'])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationProduct $product = null;

    #[ORM\ManyToMany(targetEntity: CommunicationClientPackage::class, inversedBy: 'promotionItems')]
    #[Groups(['comProm:read'])]
    #[ApiProperty]
    private Collection $products;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['comProm:read', 'comPackage:read'])]
    #[ApiProperty]
    private ?string $knowMore = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Environment $environment = null;

    #[ORM\Column(nullable: true)]
    private ?array $validityInfo = [];

    /**
     * @var Collection<int, CommunicationPricePackage>
     */
    #[ORM\ManyToMany(targetEntity: CommunicationPricePackage::class, inversedBy: 'communicationPromotions')]
    private Collection $packages;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->packages = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getInfoDescription(): ?string
    {
        return $this->infoDescription;
    }

    public function setInfoDescription(string $infoDescription): static
    {
        $this->infoDescription = $infoDescription;

        return $this;
    }

    public function getTerms(): array
    {
        return $this->terms;
    }

    public function setTerms(array $terms): static
    {
        $this->terms = $terms;

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

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getProduct(): ?CommunicationProduct
    {

        $products = $this->product;
        $productOuts = [];
        foreach ($products as $key => $product) {
            $tenant = $this->security?->getUser();
            if (!is_null(
                    $tenant
                ) && $tenant instanceof Account && $product instanceof CommunicationClientPackage && (is_null(
                        $product->getTenant()
                    ) || $product->getTenant()?->getId() === $tenant->getId())) {
                $productOuts[] = $product;
            }
        }

        return $this->product;
    }

    public function setProduct(?CommunicationProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return Collection<int, CommunicationClientPackage>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(CommunicationClientPackage $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(CommunicationClientPackage $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function getKnowMore(): ?string
    {
        return $this->knowMore;
    }

    public function setKnowMore(?string $knowMore): static
    {
        $this->knowMore = $knowMore;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreFlush]
    #[ORM\PostPersist]
    #[ORM\PreUpdate]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function setProductsTemp(Collection $products): void
    {
        $this->products = $products;
    }

    public function getValidityInfo(): ?array
    {
        return $this->validityInfo;
    }

    public function setValidityInfo(?array $validityInfo): static
    {
        $this->validityInfo = $validityInfo;

        return $this;
    }

    /**
     * @return Collection<int, CommunicationPricePackage>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(CommunicationPricePackage $package): static
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
        }

        return $this;
    }

    public function removePackage(CommunicationPricePackage $package): static
    {
        $this->packages->removeElement($package);

        return $this;
    }
}
