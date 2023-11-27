<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(
    fields: ["alpha2Code"], name: "index_alpha2_country"
)]
#[ORM\Index(
    fields: ["alpha3Code"], name: "index_alpha3_country"
)]
#[ORM\UniqueConstraint(
    name: "unique_country_codes",fields: ["alpha2Code", "alpha3Code"]
)]
#[ApiResource(
    operations: [
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['country:read']],
    denormalizationContext: ['groups' => ['country:write']]
)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['country:read'])]
    #[ApiProperty(identifier: true)]
    private ?Ulid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['country:read', 'country:write'])]
    #[ApiProperty(
        default: "Cuba",
        example: "Cuba"
    )]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(unique: true)]
    #[Groups(['country:write'])]
    #[ApiProperty(
        default: 1,
        example: 1
    )]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $rebusId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['country:write'])]
    private ?int $rebusStatusId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['country:write'])]
    private ?string $rebusStatusName = null;

    #[ORM\Column]
    #[Groups(['country:read', 'country:write'])]
    private ?bool $isActive = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    #[ApiProperty(
        default: "CU",
        example: "CU"
    )]
    private ?string $alpha2Code = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['country:read', 'country:write'])]
    #[ApiProperty(
        default: "CUB",
        example: "CUB"
    )]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    private ?string $alpha3Code = null;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Province::class)]
    private Collection $provinces;

    public function __construct()
    {
        $this->provinces = new ArrayCollection();
    }

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

    public function getRebusId(): ?int
    {
        return $this->rebusId;
    }

    public function setRebusId(int $rebusId): static
    {
        $this->rebusId = $rebusId;

        return $this;
    }

    public function getRebusStatusId(): ?int
    {
        return $this->rebusStatusId;
    }

    public function setRebusStatusId(int $rebusStatusId): static
    {
        $this->rebusStatusId = $rebusStatusId;

        return $this;
    }

    public function getRebusStatusName(): ?string
    {
        return $this->rebusStatusName;
    }

    public function setRebusStatusName(?string $rebusStatusName): static
    {
        $this->rebusStatusName = $rebusStatusName;

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

    public function getAlpha2Code(): ?string
    {
        return $this->alpha2Code;
    }

    public function setAlpha2Code(string $alpha2Code): static
    {
        $this->alpha2Code = $alpha2Code;

        return $this;
    }

    public function getAlpha3Code(): ?string
    {
        return $this->alpha3Code;
    }

    public function setAlpha3Code(string $alpha3Code): static
    {
        $this->alpha3Code = $alpha3Code;

        return $this;
    }

    /**
     * @return Collection<int, Province>
     */
    public function getProvinces(): Collection
    {
        return $this->provinces;
    }

    public function addProvince(Province $province): static
    {
        if (!$this->provinces->contains($province)) {
            $this->provinces->add($province);
            $province->setCountry($this);
        }

        return $this;
    }

    public function removeProvince(Province $province): static
    {
        if ($this->provinces->removeElement($province)) {
            // set the owning side to null (unless already changed)
            if ($province->getCountry() === $this) {
                $province->setCountry(null);
            }
        }

        return $this;
    }
}
