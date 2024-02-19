<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\DTO\CreateSaleDto;
use App\Repository\CommunicationSaleRepository;
use App\State\CreateSaleProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationSaleRepository::class)]
//#[ApiResource(
//    uriTemplate: '/communication/sales',
//    operations: [
//        new Get(
//            uriTemplate: '/communication/sales/{id}',
//            defaults: ['color' => 'brown'],
//            requirements: ['id' => '\d+'],
//        ),
//        new GetCollection(
//            uriTemplate: '/communication/sales',
//        ),
//        new Post(
//            uriTemplate: '/communication/sales',
//            input: CreateSaleDto::class,
//            processor: CreateSaleProcessor::class
//        )
//    ],
//    normalizationContext: ['groups' => ['comSales:read']],
//    denormalizationContext: ['groups' => ['comSales:update', 'comSales:create']],
//)]
#[ORM\HasLifecycleCallbacks]
class CommunicationSale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['comSales:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['comSales:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 2)]
    private ?string $type = null;

    #[ORM\Column]
    private ?int $sequenceInfo = null;

    #[ORM\Column]
    #[Groups(['comSales:create'])]
    #[Assert\NotNull]
    private ?int $packageId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['comSales:read'])]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    private ?CommunicationPackage $package = null;

    #[ORM\Column]
    #[Groups(['comSales:create', 'comSales:read'])]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['comSales:create', 'comSales:read'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $currency = null;

    #[ORM\Column]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    #[Groups(['comSales:read'])]
    private array $clientInfo = [];

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['comSales:read'])]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSequenceInfo(): ?int
    {
        return $this->sequenceInfo;
    }

    public function setSequenceInfo(int $sequenceInfo): static
    {
        $this->sequenceInfo = $sequenceInfo;

        return $this;
    }

    public function getPackageId(): ?int
    {
        return $this->packageId;
    }

    public function setPackageId(int $packageId): static
    {
        $this->packageId = $packageId;

        return $this;
    }

    public function getPackage(): ?CommunicationPackage
    {
        return $this->package;
    }

    public function setPackage(?CommunicationPackage $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getClientInfo(): array
    {
        return $this->clientInfo;
    }

    public function setClientInfo(array $clientInfo): static
    {
        $this->clientInfo = $clientInfo;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
