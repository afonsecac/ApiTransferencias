<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSaleDto
{
    #[Assert\NotNull]
    public ClientSaleDto $client;

    #[Assert\Positive]
    #[Assert\NotNull]
    public int $packageId;

    #[Assert\Length(min: 8, max: 10)]
    #[ApiProperty(
        description: 'The numbers will be preceded by 53 and so on until completing the 10 numbers.',
        example: '5355555555'
    )]
    public string $phoneNumber;
}
