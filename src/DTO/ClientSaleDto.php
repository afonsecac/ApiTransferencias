<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ClientSaleDto
{
    #[Assert\NotBlank]
    #[ApiProperty]
    #[Groups(['comSales:create'])]
    public string $identification;

    #[Assert\NotBlank]
    #[ApiProperty(
        types: 'https://scheme.org/Name'
    )]
    #[Assert\Length(min: 3)]
    #[Groups(['comSales:create'])]
    public string $name;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[ApiProperty]
    #[Groups(['comSales:create'])]
    public int $nationality;

    #[ApiProperty(
        example: '2000-01-01',
        types: 'https://schema.org/Date',
    )]
    #[Groups(['comSales:create'])]
    public \DateTime $arrivalDateAt;

    #[ApiProperty]
    #[Groups(['comSales:create'])]
    public bool $isAirport;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[ApiProperty]
    #[Groups(['comSales:create'])]
    public int $commercialOfficeId;
}
