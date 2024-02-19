<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\CommunicationRechargeRepository;
use App\State\CreateRechargeProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationRechargeRepository::class)]
//#[ApiResource(
//    uriTemplate: '/communication/recharges',
//    operations: [
//        new Get(
//            uriTemplate: '/communication/recharges/{id}',
//            defaults: ['color' => 'brown'],
//            requirements: ['id' => '\d+'],
//        ),
//        new GetCollection(
//            uriTemplate: '/communication/recharges',
//        ),
//        new Post(
//            uriTemplate: '/communication/recharges',
//            processor: CreateRechargeProcessor::class
//        )
//    ],
//    normalizationContext: ['groups' => ['comRecharges:read']],
//    denormalizationContext: ['groups' => ['comRecharges:create', 'comRecharges:update']],
//
//)]
#[ORM\HasLifecycleCallbacks]
class CommunicationRecharge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comRecharges:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    #[Assert\Length(min: 7, max: 10)]
    #[ApiProperty(
        description: '7-digit numbers will be preceded by 535, 8-digit numbers will be preceded by 53 and so on until completing the 10 numbers.',
        example: '5350499847'
    )]
    #[Groups(['comRecharges:read', 'comRecharges:create'])]
    #[Assert\NotBlank]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 20)]
    #[Groups(['comRecharges:read'])]
    private ?string $status = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    #[Groups(['comRecharges:read'])]
    private ?float $price = null;

    #[ORM\Column]
    #[Groups(['comRecharges:read', 'comRecharges:create'])]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['comRecharges:read', 'comRecharges:create'])]
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
    #[Groups(['comRecharges:read'])]
    private ?float $rate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['comRecharges:read'])]
    #[ApiProperty(
        schema: ['application/json'],
        uriTemplate: '/communication/packages/{packageId}'
    )]
    private ?CommunicationPackage $package = null;

    #[ORM\Column]
    #[Groups(['comRecharges:read', 'comRecharges:create'])]
    #[Assert\NotNull]
    #[ApiProperty(
        description: 'Id from package in /communication/packages'
    )]
    private ?int $packageId = null;

    #[ORM\Column]
    private ?int $sequence = null;

    #[ORM\Column]
    #[Groups(['comRecharges:read'])]
    private array $comInfo = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

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

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): static
    {
        $this->rate = $rate;

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

    public function getPackageId(): ?int
    {
        return $this->packageId;
    }

    public function setPackageId(int $packageId): static
    {
        $this->packageId = $packageId;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getComInfo(): array
    {
        return $this->comInfo;
    }

    public function setComInfo(array $comInfo): static
    {
        $this->comInfo = $comInfo;

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
}
