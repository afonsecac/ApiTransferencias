<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\ProvinceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProvinceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['province:read']],
    denormalizationContext: ['groups' => ['province:write']],
    security: "is_granted('ROLE_REM_API_USER')",
)]
class Province
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['province:read'])]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['province:read'])]
    #[Assert\NotBlank()]
    #[Assert\NotNull()]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $rebusProvinceId = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $rebusAbbrev = null;

    #[ORM\Column]
    #[Groups(['province:read'])]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(inversedBy: 'provinces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $country = null;

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

    public function getRebusProvinceId(): ?int
    {
        return $this->rebusProvinceId;
    }

    public function setRebusProvinceId(int $rebusProvinceId): static
    {
        $this->rebusProvinceId = $rebusProvinceId;

        return $this;
    }

    public function getRebusAbbrev(): ?string
    {
        return $this->rebusAbbrev;
    }

    public function setRebusAbbrev(?string $rebusAbbrev): static
    {
        $this->rebusAbbrev = $rebusAbbrev;

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

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

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
