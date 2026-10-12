<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entidad de ejemplo de Domain: sin dependencias de framework. Lo único de Doctrine que puede importar
 * son los atributos de mapeo (Doctrine\ORM\Mapping), que son metadatos inertes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ejemplo_pedido')]
final class Pedido
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private readonly string $id,
        #[ORM\Column(type: 'integer')]
        private int $cantidad,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function cantidad(): int
    {
        return $this->cantidad;
    }
}
