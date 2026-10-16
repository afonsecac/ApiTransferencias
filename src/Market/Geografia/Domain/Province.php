<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Provincia de Cuba (COMR-286). Su código propio no cambia. «Isla de la Juventud» es de primer nivel y va marcada como especial.
 * No se borra: se desactiva, y desactivarla oculta sus municipios sin cambiar el estado de cada uno.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_province')]
#[ORM\UniqueConstraint(name: 'uniq_market_province_code', columns: ['code'])]
class Province
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 60)]
    private string $code;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'search_name', type: 'string', length: 120)]
    private string $searchName;

    #[ORM\Column(name: 'is_special', type: 'boolean')]
    private bool $special;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    private function __construct(string $code, string $name, bool $special)
    {
        $this->code = Code::validate($code);
        $this->name = Code::validName($name);
        $this->searchName = SearchText::normalize($this->name);
        $this->special = $special;
    }

    public static function create(string $code, string $name, bool $special = false): self
    {
        return new self($code, $name, $special);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function searchName(): string
    {
        return $this->searchName;
    }

    public function isSpecial(): bool
    {
        return $this->special;
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
