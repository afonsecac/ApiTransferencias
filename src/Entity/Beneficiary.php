<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\BeneficiaryRepository;
use App\State\CreateBeneficiaryProcessor;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BeneficiaryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            processor: CreateBeneficiaryProcessor::class
        ),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['beneficiary:read']],
    denormalizationContext: ['groups' => ['beneficiary:write']],
)]
#[ORM\UniqueConstraint(
    name: "unique_beneficiary_by_environment",
    fields: ["identificationNumber", "environment"]
)]
class Beneficiary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['beneficiary:read'])]
    #[ApiProperty(identifier: true)]
    private int $id;

    #[ORM\Column(length: 60)]
    #[ApiProperty(description: "First name of beneficiary", types: ['https://schema.org/name'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[ApiProperty(description: "Middle name of beneficiary", types: ['https://schema.org/name'])]
    #[Assert\Length(max: 60)]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $middleName = null;

    #[ORM\Column(length: 120)]
    #[ApiProperty(description: "Last name of beneficiary", types: ['https://schema.org/name'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 120)]
    #[ApiProperty(description: "Email of beneficiary", types: ['https://schema.org/email'])]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[ApiProperty(description: "Phone number of beneficiary", example: "+53 5xxx xxxx")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[ApiProperty(description: "Home phone number of beneficiary", example: "+53 7xxx xxxx")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $homePhone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[ApiProperty(types: ["https://schema.org/Date"])]
    private ?DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 1, nullable: true)]
    #[Assert\Length(exactly: 1)]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ['M', 'F', 'O'],
            'description' => 'M=Male, F=Female, O=No Binary',
            'example' => 'F',
        ]
    )]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[Assert\Choice(choices: ['M', 'F', 'O'])]
    private ?string $gender = null;

    #[ORM\Column(length: 1, nullable: true)]

    #[Assert\Length(exactly: 1)]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ['M', 'F'],
            'description' => 'M=Male, F=Female',
            'example' => 'F',
        ]
    )]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[Assert\Choice(choices: ['M', 'F'])]
    private ?string $genderAtBirth = null;

    #[ORM\Column(type: Types::TEXT)]
    #[ApiProperty(description: "Principal address of beneficiary")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[Assert\NotBlank]
    private ?string $addressLine1 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[ApiProperty(description: "Second address of beneficiary")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    private ?string $addressLine2 = null;

    #[ORM\Column]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[ApiProperty(description: "Beneficiary city of residence, take from /api/cities")]
    #[Assert\Positive]
    #[Assert\NotNull]
    private ?int $cityOfResidenceId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $city = null;

    #[ORM\Column(length: 15)]
    #[ApiProperty(description: "Beneficiary zipCode", example: "10400")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 4, max: 15)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 30)]
    #[ApiProperty(description: "Beneficiary national identification", example: "20010100001")]
    #[Groups(['beneficiary:read', 'beneficiary:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 11)]
    private ?string $identificationNumber = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['beneficiary:read'])]
    private ?bool $isActive = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['beneficiary:read'])]
    #[ApiProperty(types: ["https://schema.org/DateTime"])]
    private ?DateTimeImmutable $isActiveAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $removeAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $tenant = null;

    #[ORM\OneToMany(mappedBy: 'beneficiary', targetEntity: BankCard::class)]
    #[Groups(['beneficiary:read'])]
    private Collection $bankCards;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    public function __construct()
    {
        $this->bankCards = new ArrayCollection();
    }

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

    public function setEmail(?string $email): static
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

    public function getHomePhone(): ?string
    {
        return $this->homePhone;
    }

    public function setHomePhone(?string $homePhone): static
    {
        $this->homePhone = $homePhone;

        return $this;
    }

    public function getDateOfBirth(): ?DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getGenderAtBirth(): ?string
    {
        return $this->genderAtBirth;
    }

    public function setGenderAtBirth(string $genderAtBirth): static
    {
        $this->genderAtBirth = $genderAtBirth;

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): static
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getIdentificationNumber(): ?string
    {
        return $this->identificationNumber;
    }

    public function setIdentificationNumber(string $identificationNumber): static
    {
        $this->identificationNumber = $identificationNumber;

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

    public function setIsActiveAt(DateTimeImmutable $isActiveAt): static
    {
        $this->isActiveAt = $isActiveAt;

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

    public function getTenant(): ?Account
    {
        return $this->tenant;
    }

    public function setTenant(?Account $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PreFlush]
    public function setUpdated(): void {
        $this->updatedAt = new DateTimeImmutable('now');
    }

    /**
     * @return Collection<int, BankCard>
     */
    public function getBankCards(): Collection
    {
        return $this->bankCards;
    }

    public function addBankCard(BankCard $bankCard): static
    {
        if (!$this->bankCards->contains($bankCard)) {
            $this->bankCards->add($bankCard);
            $bankCard->setBeneficiary($this);
        }

        return $this;
    }

    public function removeBankCard(BankCard $bankCard): static
    {
        if ($this->bankCards->removeElement($bankCard)) {
            // set the owning side to null (unless already changed)
            if ($bankCard->getBeneficiary() === $this) {
                $bankCard->setBeneficiary(null);
            }
        }

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

    public function getCityOfResidenceId(): ?int
    {
        return $this->cityOfResidenceId;
    }

    public function setCityOfResidenceId(?int $cityOfResidenceId): void
    {
        $this->cityOfResidenceId = $cityOfResidenceId;
    }
}
