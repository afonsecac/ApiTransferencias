<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BeneficiaryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BeneficiaryRepository::class)]
#[ApiResource]
class Beneficiary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $firstName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    private ?string $displayName = null;

    #[ORM\Column(length: 120)]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $homePhone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dob = null;

    #[ORM\Column]
    private ?int $gender = null;

    #[ORM\Column]
    private ?int $genderAtBirth = null;

    #[ORM\Column(length: 255)]
    private ?string $addressLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column]
    private ?int $provinceId = null;

    #[ORM\Column]
    private ?int $countryId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cardNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $nationalIdNumber = null;

    #[ORM\Column]
    private ?int $processorType = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column]
    private ?int $cityId = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $nationality = null;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

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

    public function getHomePhone(): ?string
    {
        return $this->homePhone;
    }

    public function setHomePhone(?string $homePhone): static
    {
        $this->homePhone = $homePhone;

        return $this;
    }

    public function getDob(): ?\DateTimeInterface
    {
        return $this->dob;
    }

    public function setDob(\DateTimeInterface $dob): static
    {
        $this->dob = $dob;

        return $this;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function setGender(int $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getGenderAtBirth(): ?int
    {
        return $this->genderAtBirth;
    }

    public function setGenderAtBirth(int $genderAtBirth): static
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getProvinceId(): ?int
    {
        return $this->provinceId;
    }

    public function setProvinceId(int $provinceId): static
    {
        $this->provinceId = $provinceId;

        return $this;
    }

    public function getCountryId(): ?int
    {
        return $this->countryId;
    }

    public function setCountryId(int $countryId): static
    {
        $this->countryId = $countryId;

        return $this;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(?string $cardNumber): static
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function getNationalIdNumber(): ?int
    {
        return $this->nationalIdNumber;
    }

    public function setNationalIdNumber(?int $nationalIdNumber): static
    {
        $this->nationalIdNumber = $nationalIdNumber;

        return $this;
    }

    public function getProcessorType(): ?int
    {
        return $this->processorType;
    }

    public function setProcessorType(int $processorType): static
    {
        $this->processorType = $processorType;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getCityId(): ?int
    {
        return $this->cityId;
    }

    public function setCityId(int $cityId): static
    {
        $this->cityId = $cityId;

        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(string $nationality): static
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

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

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
