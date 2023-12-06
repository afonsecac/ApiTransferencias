<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationProvincesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommunicationProvincesRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/communication/provinces'
        )
    ],
    normalizationContext: ['groups' => ['comProvinces:read']],
)]
class CommunicationProvinces
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['comProvinces:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comProvinces:read'])]
    #[ApiProperty]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $comId = null;

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

    public function getComId(): ?int
    {
        return $this->comId;
    }

    public function setComId(int $comId): static
    {
        $this->comId = $comId;

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
