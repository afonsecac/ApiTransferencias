<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSaleDto
{
    #[Assert\NotNull]
    #[Groups(['comSales:create'])]
    public ClientSaleDto $client;

    #[Assert\Positive]
    #[Assert\NotNull]
    #[Groups(['comSales:create'])]
    public int $packageId;

    #[Groups(['comSales:create'])]
    #[Assert\Length(min: 7, max: 10)]
    #[ApiProperty(
        description: '7-digit numbers will be preceded by 535, 8-digit numbers will be preceded by 53 and so on until completing the 10 numbers.',
        example: '5355555555'
    )]
    public string $phoneNumber;
}
