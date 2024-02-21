<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ClientSaleDto
{
    #[Assert\NotBlank]
    #[ApiProperty]
    public string $identification;

    #[Assert\NotBlank]
    #[ApiProperty(
        types: 'https://scheme.org/Name'
    )]
    #[Assert\Length(min: 3)]
    public string $name;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[ApiProperty]
    public int $nationality;

    #[ApiProperty(
        example: '2000-01-01',
        types: 'https://schema.org/Date',
    )]
    public \DateTime $arrivalDateAt;

    #[ApiProperty]
    public bool $isAirport;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[ApiProperty]
    public int $commercialOfficeId;
}
