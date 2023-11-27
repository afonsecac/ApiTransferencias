<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSenderDto
{
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 60)]
//    #[ApiProperty(description: "First name of sender", types: ['https://schema.org/name'])]
    public ?string $firstName = null;

    #[Assert\Length(min: 2, max: 60)]
    #[ApiProperty(description: "Middle name of sender", types: ['https://schema.org/name'])]
    public ?string $middleName = null;

    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 120)]
    #[ApiProperty(description: "Last name of sender", types: ['https://schema.org/name'])]
    public ?string $lastName = null;

    #[Assert\Email()]
    #[Assert\NotBlank()]
    #[ApiProperty(description: "Email to send notifications", types: ['https://schema.org/email'])]
    public ?string $email = null;

    #[ApiProperty(description: "Phone number or cell number of sender", example: "+1 709 1515 1515")]
    #[Assert\NotBlank()]
    #[Assert\Length(max: 20)]
    public ?string $phone = null;

    #[Assert\NotBlank()]
    #[ApiProperty(description: "Address of sender")]
    public ?string $address = null;

    #[Assert\Length(exactly: 3)]
    #[ApiProperty(
        example: "USA"
    )]
    #[Assert\NotBlank()]
    public ?string $countryAlpha3Code = null;

    #[ApiProperty(
        default: 'Passport',
        openapiContext: [
            'type' => 'string',
            'enum' => ['Passport', 'National Identification', 'Driver License'],
            'example' => 'Passport',
        ]
    )]
    #[Assert\NotBlank()]
    public ?string $identificationType = null;

    #[ApiProperty(identifier: true, types: ["https://schema.org/identifier"])]
    #[Assert\NotBlank()]
    #[Assert\Length(max: 255)]
    public ?string $identification = null;
    /*
        public function __construct()
        {
        }

        public function getFirstName(): ?string
        {
            return $this->firstName;
        }

        public function setFirstName(?string $firstName): void
        {
            $this->firstName = $firstName;
        }

        public function getMiddleName(): ?string
        {
            return $this->middleName;
        }

        public function setMiddleName(?string $middleName): void
        {
            $this->middleName = $middleName;
        }

        public function getLastName(): ?string
        {
            return $this->lastName;
        }

        public function setLastName(?string $lastName): void
        {
            $this->lastName = $lastName;
        }

        public function getEmail(): ?string
        {
            return $this->email;
        }

        public function setEmail(?string $email): void
        {
            $this->email = $email;
        }

        public function getPhone(): ?string
        {
            return $this->phone;
        }

        public function setPhone(?string $phone): void
        {
            $this->phone = $phone;
        }

        public function getAddress(): ?string
        {
            return $this->address;
        }

        public function setAddress(?string $address): void
        {
            $this->address = $address;
        }

        public function getCountryAlpha3Code(): ?string
        {
            return $this->countryAlpha3Code;
        }

        public function setCountryAlpha3Code(?string $countryAlpha3Code): void
        {
            $this->countryAlpha3Code = $countryAlpha3Code;
        }

        public function getIdentificationType(): ?string
        {
            return $this->identificationType;
        }

        public function setIdentificationType(?string $identificationType): void
        {
            $this->identificationType = $identificationType;
        }

        public function getIdentification(): ?string
        {
            return $this->identification;
        }

        public function setIdentification(?string $identification): void
        {
            $this->identification = $identification;
        }*/
}
