<?php

namespace App\DTO\Out;

final class ClientDto
{
    private ?string $Id;
    private ?string $Name;
    private ?string $LastName;
    private ?string $Email;
    private ?string $PhoneNumber;
    private ?string $CommercialOffice;
    private ?string $IdentificationType;
    private ?string $Street1;
    private ?string $Street2;
    private ?\DateTimeImmutable $ArrivalDate;
    private ?\DateTimeImmutable $DateOfBirth;
    private ?string $FlightDescription;
    private ?string $PickUpAirport;
    private ?string $Municipality;
    private ?string $Nationality;

    /**
     * @param string|null $Id
     * @param string|null $Name
     * @param string|null $LastName
     * @param string|null $Email
     * @param string|null $PhoneNumber
     * @param string|null $CommercialOffice
     * @param string|null $IdentificationType
     * @param string|null $Street1
     * @param string|null $Street2
     * @param \DateTimeImmutable|null $ArrivalDate
     * @param \DateTimeImmutable|null $DateOfBirth
     * @param string|null $FlightDescription
     * @param string|null $PickUpAirport
     * @param string|null $Municipality
     * @param string|null $Nationality
     */
    public function __construct(
        ?string $Id,
        ?string $Name,
        ?string $LastName,
        ?string $Email,
        ?string $PhoneNumber,
        ?string $CommercialOffice,
        ?string $IdentificationType,
        ?string $Street1,
        ?string $Street2,
        ?\DateTimeImmutable $ArrivalDate,
        ?\DateTimeImmutable $DateOfBirth,
        ?string $FlightDescription,
        ?string $PickUpAirport,
        ?string $Municipality,
        ?string $Nationality
    ) {
        $this->Id = $Id;
        $this->Name = $Name;
        $this->LastName = $LastName;
        $this->Email = $Email;
        $this->PhoneNumber = $PhoneNumber;
        $this->CommercialOffice = $CommercialOffice;
        $this->IdentificationType = $IdentificationType;
        $this->Street1 = $Street1;
        $this->Street2 = $Street2;
        $this->ArrivalDate = $ArrivalDate;
        $this->DateOfBirth = $DateOfBirth;
        $this->FlightDescription = $FlightDescription;
        $this->PickUpAirport = $PickUpAirport;
        $this->Municipality = $Municipality;
        $this->Nationality = $Nationality;
    }

    public function getId(): ?string
    {
        return $this->Id;
    }

    public function setId(?string $Id): void
    {
        $this->Id = $Id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(?string $Name): void
    {
        $this->Name = $Name;
    }

    public function getLastName(): ?string
    {
        return $this->LastName;
    }

    public function setLastName(?string $LastName): void
    {
        $this->LastName = $LastName;
    }

    public function getEmail(): ?string
    {
        return $this->Email;
    }

    public function setEmail(?string $Email): void
    {
        $this->Email = $Email;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->PhoneNumber;
    }

    public function setPhoneNumber(?string $PhoneNumber): void
    {
        $this->PhoneNumber = $PhoneNumber;
    }

    public function getCommercialOffice(): ?string
    {
        return $this->CommercialOffice;
    }

    public function setCommercialOffice(?string $CommercialOffice): void
    {
        $this->CommercialOffice = $CommercialOffice;
    }

    public function getIdentificationType(): ?string
    {
        return $this->IdentificationType;
    }

    public function setIdentificationType(?string $IdentificationType): void
    {
        $this->IdentificationType = $IdentificationType;
    }

    public function getStreet1(): ?string
    {
        return $this->Street1;
    }

    public function setStreet1(?string $Street1): void
    {
        $this->Street1 = $Street1;
    }

    public function getStreet2(): ?string
    {
        return $this->Street2;
    }

    public function setStreet2(?string $Street2): void
    {
        $this->Street2 = $Street2;
    }

    public function getArrivalDate(): ?\DateTimeImmutable
    {
        return $this->ArrivalDate;
    }

    public function setArrivalDate(?\DateTimeImmutable $ArrivalDate): void
    {
        $this->ArrivalDate = $ArrivalDate;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->DateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeImmutable $DateOfBirth): void
    {
        $this->DateOfBirth = $DateOfBirth;
    }

    public function getFlightDescription(): ?string
    {
        return $this->FlightDescription;
    }

    public function setFlightDescription(?string $FlightDescription): void
    {
        $this->FlightDescription = $FlightDescription;
    }

    public function getPickUpAirport(): ?string
    {
        return $this->PickUpAirport;
    }

    public function setPickUpAirport(?string $PickUpAirport): void
    {
        $this->PickUpAirport = $PickUpAirport;
    }

    public function getMunicipality(): ?string
    {
        return $this->Municipality;
    }

    public function setMunicipality(?string $Municipality): void
    {
        $this->Municipality = $Municipality;
    }

    public function getNationality(): ?string
    {
        return $this->Nationality;
    }

    public function setNationality(?string $Nationality): void
    {
        $this->Nationality = $Nationality;
    }
}