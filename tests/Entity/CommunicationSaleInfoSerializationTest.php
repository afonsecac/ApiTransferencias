<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationSaleInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Regresión: el grupo 'comSales:read' alimenta la respuesta pública de
 * /api/communication/sale/* (API Platform, consumida por la app móvil).
 * $provider y $transactionStatus revelan qué proveedor interno (CSQ/DTOne/
 * ETECSA) procesó la venta y no deben ser visibles al cliente externo.
 *
 * @covers \App\Entity\CommunicationSaleInfo
 */
class CommunicationSaleInfoSerializationTest extends TestCase
{
    /**
     * @dataProvider providerLeakingProperties
     */
    public function testProviderSensitivePropertiesAreNotInPublicApiGroup(string $property): void
    {
        $reflection = new \ReflectionProperty(CommunicationSaleInfo::class, $property);
        $attributes = $reflection->getAttributes(Groups::class);

        self::assertNotEmpty($attributes, "La propiedad {$property} debería tener un atributo #[Groups].");

        /** @var Groups $groups */
        $groups = $attributes[0]->newInstance();

        self::assertNotContains(
            'comSales:read',
            $groups->groups,
            "La propiedad {$property} expone el proveedor interno y no debe pertenecer al grupo 'comSales:read' (API pública)."
        );
    }

    public static function providerLeakingProperties(): array
    {
        return [
            'provider' => ['provider'],
            'transactionStatus' => ['transactionStatus'],
        ];
    }

    public function testStateStaysVisibleToClientsForOutcomeTracking(): void
    {
        $reflection = new \ReflectionProperty(CommunicationSaleInfo::class, 'state');
        $attributes = $reflection->getAttributes(Groups::class);

        self::assertNotEmpty($attributes);

        /** @var Groups $groups */
        $groups = $attributes[0]->newInstance();

        self::assertContains(
            'comSales:read',
            $groups->groups,
            "El cliente externo debe poder seguir viendo 'state' para saber si la venta se completó, aunque no vea el proveedor."
        );
    }
}
