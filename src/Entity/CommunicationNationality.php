<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CommunicationNationalityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommunicationNationalityRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/communication/nationalities'
        )
    ],
    normalizationContext: ['groups' => ['comNationality:read']],
    security: "is_granted('ROLE_COM_API_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['codeAlpha3', 'name'])]
class CommunicationNationality
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['comNationality:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Environment $environment = null;

    #[ORM\Column(length: 255)]
    #[Groups(['comNationality:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['comNationality:read'])]
    private ?string $codeAlpha2 = null;

    #[ORM\Column(length: 3)]
    #[Groups(['comNationality:read'])]
    private ?string $codeAlpha3 = null;

    #[ORM\Column]
    private ?int $comId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCodeAlpha2(): ?string
    {
        return $this->codeAlpha2;
    }

    public function setCodeAlpha2(?string $codeAlpha2): static
    {
        $this->codeAlpha2 = $codeAlpha2;

        return $this;
    }

    public function getCodeAlpha3(): ?string
    {
        return $this->codeAlpha3;
    }

    public function setCodeAlpha3(string $codeAlpha3): static
    {
        $this->codeAlpha3 = $codeAlpha3;

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
}
