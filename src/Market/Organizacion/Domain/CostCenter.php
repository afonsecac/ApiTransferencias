<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Centro de costo de una unidad organizativa (COMR-271), una fila por centro para poder buscarlos y hacer
 * reportes por ellos. Se identifica por su código dentro de la unidad. Un centro no se borra, se desactiva:
 * lo que se impute a él después debe seguir teniendo a quién apuntar.
 *
 * Las reglas entre los centros de una unidad (uno principal, códigos únicos) las aplica CostCenterSet; la
 * base lo refuerza con un índice único por código y una restricción de un solo principal por unidad.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_cost_center')]
#[ORM\UniqueConstraint(name: 'uniq_market_cost_center_code', columns: ['org_unit_id', 'code'])]
#[ORM\Index(name: 'idx_market_cost_center_code', columns: ['code'])]
class CostCenter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OrgUnit::class)]
    #[ORM\JoinColumn(name: 'org_unit_id', nullable: false, onDelete: 'RESTRICT')]
    private OrgUnit $orgUnit;

    #[ORM\Column(type: 'string', length: 30)]
    private string $code;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $principal;

    #[ORM\Column(type: 'boolean')]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(OrgUnit $orgUnit, CostCenterDraft $draft, bool $principal, bool $active = true)
    {
        $this->orgUnit = $orgUnit;
        $this->code = $draft->code;
        $this->name = $draft->name;
        $this->principal = $principal;
        $this->active = $active;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function orgUnit(): OrgUnit
    {
        return $this->orgUnit;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isPrincipal(): bool
    {
        return $this->principal;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @internal solo CostCenterSet, que es quien conoce las reglas entre los centros de la unidad */
    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidOrgUnit(sprintf('El centro de costo "%s" necesita un nombre.', $this->code));
        }
        $this->name = trim($name);
    }

    /** @internal solo CostCenterSet */
    public function setPrincipal(bool $principal): void
    {
        $this->principal = $principal;
    }

    /** @internal solo CostCenterSet */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
