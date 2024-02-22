<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\CommunicationSalePackageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationSalePackageRepository::class)]
#[ApiResource(
    operations: [],
    security: "is_granted('ROLE_COM_API_USER')",
)]
class CommunicationSalePackage extends CommunicationSaleInfo
{
    #[ORM\Column(length: 15, nullable: true)]
    #[Assert\NotBlank]
    #[ApiProperty(
        description: 'Document identification of the client',
        required: true
    )]
    #[Groups(['comSales:read', 'comSales:create'])]
    private ?string $identificationNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank]
    #[ApiProperty(
        description: 'Identification name of the client',
        required: true,
        types: 'https://scheme.org/Name'
    )]
    #[Assert\Length(min: 3)]
    #[Groups(['comSales:read', 'comSales:create'])]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['comSales:read'])]
    private ?CommunicationOffice $commercialOffice = null;

    #[ApiProperty(
        description: 'The nationality id from /communication/nationalities',
        required: true
    )]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['comSales:create'])]
    public int $nationalityId;

    #[ApiProperty(
        description: 'The commercial office id from /communication/offices',
        required: true
    )]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['comSales:create'])]
    public int $commercialOfficeId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['comSales:read'])]
    private ?CommunicationNationality $nationality = null;

    #[ORM\Column(nullable: true)]
    #[ApiProperty(
        required: false,
        example: '2000-01-01',
        types: 'https://schema.org/Date',
    )]
    #[Groups(['comSales:read', 'comSales:create'])]
    private ?\DateTimeImmutable $arrivalAt = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Groups(['comSales:read', 'comSales:create'])]
    #[ApiProperty(
        description: 'Only send if the customer has a contact number',
        required: false
    )]
    private ?string $phoneClientNumber = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['comSales:read'])]
    #[ApiProperty(
        description: 'If the is airport'
    )]
    private ?bool $officeIsAirport = null;

    public function getIdentificationNumber(): ?string
    {
        return $this->identificationNumber;
    }

    public function setIdentificationNumber(string $identificationNumber): static
    {
        $this->identificationNumber = $identificationNumber;

        return $this;
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

    public function getCommercialOffice(): ?CommunicationOffice
    {
        return $this->commercialOffice;
    }

    public function setCommercialOffice(?CommunicationOffice $commercialOffice): static
    {
        $this->commercialOffice = $commercialOffice;

        return $this;
    }

    public function getNationality(): ?CommunicationNationality
    {
        return $this->nationality;
    }

    public function setNationality(?CommunicationNationality $nationality): static
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function getArrivalAt(): ?\DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function setArrivalAt(?\DateTimeImmutable $arrivalAt): static
    {
        $this->arrivalAt = $arrivalAt;

        return $this;
    }

    public function getPhoneClientNumber(): ?string
    {
        return $this->phoneClientNumber;
    }

    public function setPhoneClientNumber(?string $phoneClientNumber): static
    {
        $this->phoneClientNumber = $phoneClientNumber;

        return $this;
    }

    public function isOfficeIsAirport(): ?bool
    {
        return $this->officeIsAirport;
    }

    public function setOfficeIsAirport(?bool $officeIsAirport): static
    {
        $this->officeIsAirport = $officeIsAirport;

        return $this;
    }
}
