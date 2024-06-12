<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationPromotionsRepository;
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
        ),
    ],
    normalizationContext: ['groups' => ['comProm:read']],
    denormalizationContext: ['groups' => ['comProm:create', 'comProm:update']],
    order: ['packageClientPrice.amount' => 'DESC'],
    security: "is_granted('ROLE_COM_API_USER')"
)]
class CommunicationPromotions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comProm:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comProm:read'])]
    #[ApiProperty()]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comProm:read'])]
    #[ApiProperty()]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['comProm:read'])]
    #[ApiProperty()]
    private ?string $infoDescription = null;

    #[ORM\Column]
    #[ApiProperty(
        description: 'List of benefits and terms',
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'additional_information' => [
                        'type' => 'string',
                    ],
                    'amount' => [
                        'type' => 'object',
                        'properties' => [
                            'base' => [
                                'type' => 'integer',
                            ],
                            'promotion_bonus' => [
                                'type' => 'integer',
                            ],
                            'total_excluding_tax' => [
                                'type' => 'integer',
                            ],
                            'total_including_tax' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['CREDITS', 'TALKTIME', 'DATA', 'SMS'],
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['CUP', 'USD', 'UNITS', 'MINUTES', 'GB', 'ILIM'],
                    ],
                    'unit_type' => [
                        'type' => 'string',
                        'enum' => ['CURRENCY', 'QUANTITY', 'DATA', 'TIME'],
                    ],
                    'schedule' => [
                        'type' => 'object',
                        'properties' => [
                            'start' => [
                                'type' => 'string',
                            ],
                            'end' => [
                                'type' => 'string',
                                'nullable' => true,
                                'default' => null,
                            ],
                        ],
                        'default' => null,
                        'nullable' => true,
                    ],
                ],
            ],
        ]
    )]
    #[Groups(['comProm:read'])]
    private array $terms = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    #[Groups(['comProm:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    #[Groups(['comProm:read'])]
    #[Assert\NotNull]
    #[ApiProperty(types: 'https://scheme.org/DateTime')]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\ManyToMany(targetEntity: CommunicationClientPackage::class, inversedBy: 'currentPromotions')]
    #[Groups(['comProm:read'])]
    #[ApiProperty]
    private Collection $products;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationProduct $product = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
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

    /**
     * @return Collection<int, CommunicationPackage>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(CommunicationPackage $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(CommunicationPackage $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function getTenant(): ?Account
    {
        return $this->tenant;
    }

    public function setTenant(?Account $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getProduct(): ?CommunicationProduct
    {
        return $this->product;
    }

    public function setProduct(?CommunicationProduct $product): static
    {
        $this->product = $product;

        return $this;
    }
}
