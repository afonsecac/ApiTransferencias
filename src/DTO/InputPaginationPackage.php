<?php

namespace App\DTO;


use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
class InputPaginationPackage
{
    #[Groups('comPackage:read')]
    #[ApiProperty(
        description: 'Page offset',
        default: 0,
    )]
    #[Assert\PositiveOrZero]
    #[Assert\NotBlank]
    public int $page = 0;
    public int $itemsPerPage = 10;
    public string $tag;
    public string $serviceName;
    public string $packageName;
    public string $packageDescription;
    public string $orderBy;
}
