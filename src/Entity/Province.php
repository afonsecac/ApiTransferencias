<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\ProvinceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use ApiPlatform\Doctrine\Orm\State\Options;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProvinceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: "/countries/{countryId}/provinces",
            uriVariables: [
                "countryId" => new Link(
                    fromProperty: "country",
                    fromClass: Country::class
                )
            ],
            stateOptions: new Options(handleLinks: [Province::class, 'handleLinks'])
        )
    ],
    normalizationContext: ['groups' => ['province:read']],
    denormalizationContext: ['groups' => ['province:write']]
)]
class Province
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['province:read'])]
    #[ApiProperty(identifier: true)]
    private ?Ulid $id = null;

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
}
