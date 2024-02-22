<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\CommunicationSaleRechargeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationSaleRechargeRepository::class)]
#[ApiResource(
    operations: [],
    security: "is_granted('ROLE_COM_API_USER')",
)]
class CommunicationSaleRecharge extends CommunicationSaleInfo
{
    #[ORM\Column(length: 15, nullable: true)]
    #[ApiProperty(
        description: 'The numbers will be preceded by 53 and so on until completing the 10 numbers.',
        required: true,
        example: '5350499847',
    )]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 10)]
    #[Groups(['comSales:read', 'comSales:create'])]
    private string $phoneNumber;

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }
}
