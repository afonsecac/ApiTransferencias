<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\CommunicationOfficeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommunicationOfficeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/communication/offices'
        ),
        new GetCollection(
            uriTemplate: '/communication/provinces/{provinceId}/offices',
            uriVariables: [
                'provinceId' => new Link(
                    toProperty: 'province',
                    fromClass: CommunicationProvinces::class
                ),
            ]
        ),
    ],
    normalizationContext: ['groups' => ['comOffices:read']],
)]
class CommunicationOffice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['comOffices:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comOffices:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $comId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(
        schema: ['application/json'],
    )]
    private ?CommunicationProvinces $province = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['comOffices:read'])]
    private ?bool $isAirport = null;

    #[ORM\Column]
    #[Groups(['comOffices:read'])]
    private ?bool $isActive = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getComId(): ?string
    {
        return $this->comId;
    }

    public function setComId(string $comId): static
    {
        $this->comId = $comId;

        return $this;
    }

    public function getProvince(): ?CommunicationProvinces
    {
        return $this->province;
    }

    public function setProvince(?CommunicationProvinces $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function isIsAirport(): ?bool
    {
        return $this->isAirport;
    }

    public function setIsAirport(?bool $isAirport): static
    {
        $this->isAirport = $isAirport;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): static
    {
        $this->environment = $environment;

        return $this;
    }
}
