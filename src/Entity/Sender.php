<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SenderRepository;
use App\State\CreateSenderProcessor;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SenderRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            processor: CreateSenderProcessor::class
        ),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['sender:read']],
    denormalizationContext: ['groups' => ['sender:write']],
)]
#[ApiFilter(DateFilter::class, properties: ['dateOfBirth'])]
#[ApiFilter(SearchFilter::class, properties: [
    'identification' => 'partial',
    'firstName' => 'partial',
    'lastName' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'firstName' => 'ASC',
    'lastName' => 'ASC',
    'identification' => 'ASC',
    'dateOfBirth' => 'DESC',
])]
#[ORM\UniqueConstraint(
    name: "unique_identification_sender",
    fields: ["identificationType", "identification"]
)]
#[ORM\UniqueConstraint(
    name: "unique__rebus_identification_sender",
    fields: ["rebusSenderId", "identification"]
)]
#[ORM\Index(
    fields: ["identification"],
    name: "index_identification_sender"
)]
#[ORM\HasLifecycleCallbacks]
class Sender
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sender:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 60)]
    #[ApiProperty(description: "First name of sender", types: ['https://schema.org/name'])]
    #[Groups(['sender:read', 'sender:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(min: 2, max: 60)]
    #[ApiProperty(description: "Middle name of sender", types: ['https://schema.org/name'])]
    #[Groups(['sender:read', 'sender:write'])]
    private ?string $middleName = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 120)]
    #[ApiProperty(description: "Last name of sender", types: ['https://schema.org/name'])]
    #[Groups(['sender:read', 'sender:write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 120)]
    #[Assert\Email()]
    #[Assert\NotBlank()]
    #[ApiProperty(description: "Email to send notifications", types: ['https://schema.org/email'])]
    #[Groups(['sender:read', 'sender:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    #[ApiProperty(description: "Phone number or cell number of sender", example: "+1 709 1515 1515")]
    #[Groups(['sender:read', 'sender:write'])]
    #[Assert\NotBlank()]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['sender:read', 'sender:write'])]
    #[Assert\NotBlank()]
    #[ApiProperty(description: "Address of sender")]
    private ?string $address = null;

    #[ORM\Column(length: 3)]
    #[Assert\Length(exactly: 3)]
    #[Groups(['sender:read', 'sender:write'])]
    #[ApiProperty(
        example: "USA"
    )]
    #[Assert\NotBlank()]
    private ?string $countryAlpha3Code = null;

    #[ORM\Column(length: 50)]
    #[ApiProperty(
        default: 'NI',
        openapiContext: [
            'type' => 'string',
            'enum' => ['P', 'NI', 'DL'],
            'description' => 'P=Passport, NI=National Identification, DL=Driver License',
            'example' => 'NI',
        ]
    )]
    #[Assert\NotBlank()]
    #[Groups(['sender:read', 'sender:write'])]
    #[Assert\Choice(choices: ['P', 'NI', 'DL'])]
    private ?string $identificationType = null;

    #[ORM\Column(length: 255)]
    #[ApiProperty(identifier: true, types: ["https://schema.org/identifier"])]
    #[Assert\NotBlank()]
    #[Assert\Length(max: 255)]
    #[Groups(['sender:read', 'sender:write'])]
    private ?string $identification = null;

    #[ORM\Column(nullable: true)]
    private ?int $rebusSenderId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['sender:read', 'sender:write'])]
    #[ApiProperty(types: ["https://schema.org/Date"])]
    private ?DateTimeInterface $dateOfBirth = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCountryAlpha3Code(): ?string
    {
        return $this->countryAlpha3Code;
    }

    public function setCountryAlpha3Code(string $countryAlpha3Code): static
    {
        $this->countryAlpha3Code = $countryAlpha3Code;

        return $this;
    }

    public function getIdentificationType(): ?string
    {
        return $this->identificationType;
    }

    public function setIdentificationType(string $identificationType): static
    {
        $this->identificationType = $identificationType;

        return $this;
    }

    public function getIdentification(): ?string
    {
        return $this->identification;
    }

    public function setIdentification(string $identification): static
    {
        $this->identification = $identification;

        return $this;
    }

    public function getRebusSenderId(): ?int
    {
        return $this->rebusSenderId;
    }

    public function setRebusSenderId(int $rebusSenderId): static
    {
        $this->rebusSenderId = $rebusSenderId;

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

    public function getDateOfBirth(): ?DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

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

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
