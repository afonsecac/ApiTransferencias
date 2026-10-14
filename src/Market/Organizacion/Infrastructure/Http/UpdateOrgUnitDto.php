<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/org-units/{id}. El país no se cambia y los centros de costo se
 * gestionan por sus propios endpoints.
 */
class UpdateOrgUnitDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    protected ?string $legalName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    protected ?string $taxId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    protected ?string $address;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    protected ?string $billingEmail;

    /** @var string[]|null */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Choice(choices: ['platform', 'seller', 'warehouse_partner', 'carrier', 'buyer'])])]
    protected ?array $types;

    /** @var array<string, mixed>|null */
    protected ?array $fiscalData;

    /**
     * @param string[]|null $types
     * @param array<string, mixed>|null $fiscalData
     */
    public function __construct(
        ?string $name = null,
        ?string $legalName = null,
        ?string $taxId = null,
        ?string $address = null,
        ?string $billingEmail = null,
        ?array $types = null,
        ?array $fiscalData = null,
    ) {
        $this->name = $name;
        $this->legalName = $legalName;
        $this->taxId = $taxId;
        $this->address = $address;
        $this->billingEmail = $billingEmail;
        $this->types = $types;
        $this->fiscalData = $fiscalData;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(?string $legalName): void
    {
        $this->legalName = $legalName;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): void
    {
        $this->billingEmail = $billingEmail;
    }

    /**
     * @return string[]|null
     */
    public function getTypes(): ?array
    {
        return $this->types;
    }

    /**
     * @param string[]|null $types
     */
    public function setTypes(?array $types): void
    {
        $this->types = $types;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFiscalData(): ?array
    {
        return $this->fiscalData;
    }

    /**
     * @param array<string, mixed>|null $fiscalData
     */
    public function setFiscalData(?array $fiscalData): void
    {
        $this->fiscalData = $fiscalData;
    }
}
