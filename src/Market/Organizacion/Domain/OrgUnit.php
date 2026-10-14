<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Unidad organizativa (COMR-271): una empresa o socio con su país, sus datos fiscales y sus centros de
 * costo. Puede ser varias cosas a la vez (plataforma, vendedor, socio de almacén, transportista,
 * comprador). Los datos fiscales propios del país se validan fuera, contra el registro fiscal del país
 * (COMR-270); aquí solo se guardan.
 *
 * Invariantes: al menos un tipo; al menos un centro de costo y exactamente uno principal y activo; el país
 * no cambia después de crearla (cambiarlo invalidaría sus datos fiscales).
 *
 * Los centros de costo viven dentro de la unidad (JSON): se cambian siempre con ella y así el invariante
 * del principal se cumple en una sola escritura.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_org_unit')]
#[ORM\UniqueConstraint(name: 'uniq_market_org_unit_tax_id', columns: ['country_code', 'tax_id'])]
class OrgUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'legal_name', type: 'string', length: 200)]
    private string $legalName;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(name: 'tax_id', type: 'string', length: 60)]
    private string $taxId;

    #[ORM\Column(type: 'string', length: 300)]
    private string $address;

    #[ORM\Column(name: 'billing_email', type: 'string', length: 180)]
    private string $billingEmail;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $types = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'fiscal_data', type: 'json')]
    private array $fiscalData = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<OrgUnitType> $types
     * @param array<string, mixed> $fiscalData
     */
    public function __construct(
        string $name,
        string $legalName,
        string $countryCode,
        string $taxId,
        string $address,
        string $billingEmail,
        array $types,
        array $fiscalData,
    ) {
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1 || $countryCode === 'ZZ') {
            throw new InvalidOrgUnit(sprintf('El país "%s" no es válido: dos letras mayúsculas (ZZ está reservado al registro fiscal genérico).', $countryCode));
        }
        $this->countryCode = $countryCode;
        $this->applyData($name, $legalName, $taxId, $address, $billingEmail, $types, $fiscalData);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function taxId(): string
    {
        return $this->taxId;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function billingEmail(): string
    {
        return $this->billingEmail;
    }

    /**
     * @return list<OrgUnitType>
     */
    public function types(): array
    {
        return array_map(static fn (string $t) => OrgUnitType::from($t), $this->types);
    }

    /**
     * @return array<string, mixed>
     */
    public function fiscalData(): array
    {
        return $this->fiscalData;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<OrgUnitType> $types
     * @param array<string, mixed> $fiscalData
     */
    public function update(
        string $name,
        string $legalName,
        string $taxId,
        string $address,
        string $billingEmail,
        array $types,
        array $fiscalData,
    ): void {
        $this->applyData($name, $legalName, $taxId, $address, $billingEmail, $types, $fiscalData);
        $this->touch();
    }

    /**
     * @param list<OrgUnitType> $types
     * @param array<string, mixed> $fiscalData
     */
    private function applyData(
        string $name,
        string $legalName,
        string $taxId,
        string $address,
        string $billingEmail,
        array $types,
        array $fiscalData,
    ): void {
        $name = trim($name);
        $legalName = trim($legalName);
        $taxId = strtoupper(trim($taxId));
        $address = trim($address);
        $billingEmail = trim($billingEmail);

        if ($name === '' || $legalName === '' || $taxId === '' || $address === '') {
            throw new InvalidOrgUnit('El nombre, la razón social, el identificador fiscal y la dirección son obligatorios.');
        }
        if (filter_var($billingEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidOrgUnit(sprintf('El correo de facturación "%s" no es válido.', $billingEmail));
        }
        $uniqueTypes = array_values(array_unique(array_map(static fn (OrgUnitType $t) => $t->value, $types)));
        if ($uniqueTypes === []) {
            throw new InvalidOrgUnit('La unidad necesita al menos un tipo.');
        }

        $this->name = $name;
        $this->legalName = $legalName;
        $this->taxId = $taxId;
        $this->address = $address;
        $this->billingEmail = $billingEmail;
        $this->types = $uniqueTypes;
        $this->fiscalData = $fiscalData;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
