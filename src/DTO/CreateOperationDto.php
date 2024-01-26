<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateOperationDto
{
    #[Groups(['balance:create'])]
    #[Assert\Positive]
    #[Assert\NotNull]
    public float $amount;

    #[Groups(['balance:create'])]
    #[ApiProperty(
        default: 'USD',
        openapiContext: [
            'type' => 'string',
            'enum' => ['USD', 'EUR'],
            'description' => 'USD=US Dollar, EUR=Euro',
            'example' => 'USD',
        ],
        types: 'https://scheme.org/priceCurrency'
    )]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Assert\Length(exactly: 3)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    public string $currency;
}
