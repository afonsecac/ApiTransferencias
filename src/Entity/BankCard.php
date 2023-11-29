<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\BankCardRepository;
use App\State\CreateBeneficiaryCardProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BankCardRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/bankCards/{id}',
            uriVariables: 'id'
        ),
        new GetCollection(
            uriTemplate: '/bankCards'
        ),
        new Post(
            uriTemplate: '/bankCards',
            processor: CreateBeneficiaryCardProcessor::class
        ),
        new Patch(
            uriTemplate: '/bankCards/{id}',
            uriVariables: 'id'
        ),
        new Delete(
            uriTemplate: '/bankCards/{id}',
            uriVariables: 'id'
        ),
    ],
    normalizationContext: ['groups' => ['bankCard:read']],
    denormalizationContext: ['groups' => ['bankCard:write']],
)]
#[ORM\UniqueConstraint(
    name: "unique_card_by_beneficiary",
    fields: ["cardNumber", "beneficiaryCardId"]
)]
#[ORM\UniqueConstraint(
    name: "unique_card_by_beneficiary_tenant",
    fields: ["cardNumber", "beneficiaryCardId", "beneficiary.tenant"]
)]
#[ORM\HasLifecycleCallbacks]
class BankCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups(['bankCard:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bankCards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Beneficiary $beneficiary = null;

    #[ORM\Column]
    private ?int $rebusId = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 20)]
    #[ApiProperty(example: "92049598xxxxxxxx", types: ['https://schema.org/identifier'])]
    #[Groups(['bankCard:read', 'bankCard:write'])]
//    #[Assert\CardScheme(
//        schemes: [],
//        message: "Beneficiary bank account"
//    )]
    #[Assert\Length(exactly: 16)]
    #[Assert\NotBlank]
    private ?string $cardNumber = null;

    #[ORM\Column]
    #[Groups(['bankCard:read', 'bankCard:write'])]
    #[ApiProperty(description: "Beneficiary information")]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $beneficiaryCardId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBeneficiary(): ?Beneficiary
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?Beneficiary $beneficiary): static
    {
        $this->beneficiary = $beneficiary;

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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(string $cardNumber): static
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function getBeneficiaryCardId(): ?int
    {
        return $this->beneficiaryCardId;
    }

    public function setBeneficiaryCardId(int $beneficiaryCardId): static
    {
        $this->beneficiaryCardId = $beneficiaryCardId;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreated(): void
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PostRemove]
    #[ORM\PrePersist]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
