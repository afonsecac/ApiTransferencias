<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: "/provinces/{provinceId}/cities",
            uriVariables: [
                "provinceId" => new Link(
                    fromProperty: "province",
                    fromClass: Province::class
                )
            ],
            stateOptions: new Options(handleLinks: [City::class, 'handleLinks'])
        )
    ],
    normalizationContext: ['groups' => ['city:read']],
    denormalizationContext: ['groups' => ['city:write']]
)]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['city:read'])]
    #[ApiProperty(identifier: true)]
    private ?Ulid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['city:read'])]
    #[Assert\NotBlank()]
    #[Assert\NotNull()]
    private ?string $name = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $rebusAbbrev = null;

    #[ORM\Column]
    #[Groups(['city:read'])]
    private ?bool $isActive = null;

    public function getId(): ?Ulid
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
}
