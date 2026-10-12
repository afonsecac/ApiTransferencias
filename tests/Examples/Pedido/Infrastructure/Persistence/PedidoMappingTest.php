<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure\Persistence;

use App\Tests\Examples\Pedido\Domain\Pedido;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\ORM\Tools\SchemaValidator;

/**
 * Convención para entidades de Domain: se mapean con los atributos de Doctrine\ORM\Mapping (únicos
 * símbolos de Doctrine permitidos en Domain) y cada contexto registra su carpeta Domain en
 * config/packages/doctrine.yaml.
 */
final class PedidoMappingTest extends FunctionalTestCase
{
    public function testDomainEntityIsMappedWithAttributes(): void
    {
        $metadata = $this->em->getClassMetadata(Pedido::class);

        self::assertSame('ejemplo_pedido', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertEqualsCanonicalizing(['id', 'cantidad'], $metadata->getFieldNames());
    }

    public function testMappingIsValid(): void
    {
        $errors = (new SchemaValidator($this->em))->validateClass($this->em->getClassMetadata(Pedido::class));

        self::assertSame([], $errors);
    }
}
