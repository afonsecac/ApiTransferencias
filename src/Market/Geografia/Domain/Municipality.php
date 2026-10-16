<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Municipio de Cuba (COMR-286). El código propio no cambia; el código de la ONEI es opcional y solo se rellena cuando hay una
 * fuente oficial. Un municipio inactivo no se puede elegir ni entregar, pero sigue existiendo para lo ya registrado.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_municipality')]
#[ORM\UniqueConstraint(name: 'uniq_market_municipality_code', columns: ['code'])]
#[ORM\Index(name: 'idx_market_municipality_province', columns: ['province_id'])]
#[ORM\Index(name: 'idx_market_municipality_search', columns: ['search_name'])]
class Municipality
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'province_id', type: 'integer')]
    private int $provinceId;

    #[ORM\Column(type: 'string', length: 60)]
    private string $code;

    #[ORM\Column(name: 'onei_code', type: 'string', length: 10, nullable: true)]
    private ?string $oneiCode = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'search_name', type: 'string', length: 120)]
    private string $searchName;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    private function __construct(int $provinceId, string $code, string $name)
    {
        if ($provinceId <= 0) {
            throw new InvalidGeography('Un municipio no se guarda sin provincia.');
        }
        $this->provinceId = $provinceId;
        $this->code = Code::validate($code);
        $this->name = Code::validName($name);
        $this->searchName = SearchText::normalize($this->name);
    }

    public static function create(int $provinceId, string $code, string $name): self
    {
        return new self($provinceId, $code, $name);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function provinceId(): int
    {
        return $this->provinceId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function oneiCode(): ?string
    {
        return $this->oneiCode;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function searchName(): string
    {
        return $this->searchName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
