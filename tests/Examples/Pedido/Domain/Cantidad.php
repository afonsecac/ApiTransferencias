<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Domain;

/** Objeto de valor de ejemplo: no importa Symfony ni Doctrine (regla de Domain de ADR-001). */
final readonly class Cantidad
{
    public function __construct(public int $valor)
    {
        if ($valor <= 0) {
            throw new \DomainException('La cantidad debe ser positiva.');
        }
    }

    public function sumar(self $otra): self
    {
        return new self($this->valor + $otra->valor);
    }
}
