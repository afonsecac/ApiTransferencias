<?php

namespace App\Entity;

use App\Repository\CommunicationSalePackageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationSalePackageRepository::class)]
class CommunicationSalePackage extends CommunicationSaleInfo
{
    #[ORM\Column(length: 15)]
    private ?string $identificationNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationOffice $commercialOffice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommunicationNationality $nationality = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $arrivalAt = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $phoneClientNumber = null;

    #[ORM\Column(nullable: true)]
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
