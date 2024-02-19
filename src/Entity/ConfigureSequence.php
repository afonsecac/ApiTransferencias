<?php

namespace App\Entity;

use App\Repository\ConfigureSequenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigureSequenceRepository::class)]
#[ORM\UniqueConstraint(
    name: 'sequence_unique_by_data',
    fields: ['year', 'operationClass']
)]
class ConfigureSequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 4)]
    private ?string $year = null;

    #[ORM\Column]
    private ?int $sequenceValue = null;

    #[ORM\Column(length: 255)]
    private ?string $operationClass = null;

    /**
     * @param string|null $operationClass
     * @param string|null $year
     */
    public function __construct(?string $operationClass = null, ?string $year = null)
    {
        $this->operationClass = $operationClass;
        $this->year = $year;
        $this->sequenceValue = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function setYear(string $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getSequenceValue(): ?int
    {
        return $this->sequenceValue;
    }

    public function setSequenceValue(int $sequenceValue): static
    {
        $this->sequenceValue = $sequenceValue;

        return $this;
    }

    public function getOperationClass(): ?string
    {
        return $this->operationClass;
    }

    public function setOperationClass(string $operationClass): static
    {
        $this->operationClass = $operationClass;

        return $this;
    }
}
