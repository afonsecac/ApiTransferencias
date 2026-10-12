<?php

declare(strict_types=1);

namespace App\Market\Violation\Domain;

use Doctrine\ORM\Mapping as ORM;

/** Fixture permitido: Domain puede llevar atributos de mapeo de Doctrine (metadatos inertes). */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_entidad')]
final class EntidadConAtributos
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function id(): string
    {
        return $this->id;
    }
}
