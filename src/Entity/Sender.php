<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Processor\CreateSenderProcessor;
use App\Repository\SenderRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Ulid;
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
    denormalizationContext: ['groups' => ['sender:write']]
)]
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
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['sender:read'])]
    #[ApiProperty(identifier: true)]
    private ?Ulid $id = null;

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
        default: 'Passport',
        openapiContext: [
            'type' => 'string',
            'enum' => ['Passport', 'National Identification', 'Driver License'],
            'example' => 'Passport',
        ]
    )]
    #[Assert\NotBlank()]
    #[Groups(['sender:read', 'sender:write'])]
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
    private ?Permission $tenant = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['sender:read', 'sender:write'])]
    #[ApiProperty(example: "2000-01-01", types: ["https://schema.org/date"])]
    #[Assert\Date]
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d']
    )]
    private ?DateTimeInterface $dateOfBirth = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?Ulid
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

    public function getTenant(): ?Permission
    {
        return $this->tenant;
    }

    public function setTenant(?Permission $tenant): static
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
