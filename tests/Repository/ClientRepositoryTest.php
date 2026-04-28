<?php

namespace App\Tests\Repository;

use App\Repository\ClientRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Repository\ClientRepository
 */
class ClientRepositoryTest extends TestCase
{
    public function testSortableWhitelistContainsOnlyKnownFields(): void
    {
        $reflection = new ReflectionClass(ClientRepository::class);
        $constant   = $reflection->getConstant('SORTABLE');

        $this->assertIsArray($constant);

        foreach ($constant as $alias => $dqlField) {
            // Todos los campos deben referenciar el alias 'c.'
            $this->assertStringStartsWith('c.', $dqlField, "El campo '$alias' debe mapear a 'c.<campo>'");
            // Las claves no deben contener caracteres peligrosos
            $this->assertMatchesRegularExpression('/^[a-zA-Z]+$/', $alias, "La clave de SORTABLE '$alias' no debe contener caracteres especiales");
        }
    }

    public function testSortableDoesNotAcceptArbitraryInput(): void
    {
        $reflection = new ReflectionClass(ClientRepository::class);
        $sortable   = $reflection->getConstant('SORTABLE');

        $maliciousInputs = [
            'id; DROP TABLE client--',
            '1=1',
            'companyName) OR (1',
            '../secret',
        ];

        foreach ($maliciousInputs as $input) {
            $this->assertArrayNotHasKey(
                $input,
                $sortable,
                "La entrada maliciosa '$input' no debe existir en SORTABLE"
            );
        }
    }
}
