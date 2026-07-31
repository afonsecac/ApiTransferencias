<?php

namespace App\Tests\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\TouristSimProviderInterface;
use App\Provider\ProviderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\ProviderRegistry
 */
class ProviderRegistryTest extends TestCase
{
    public function testGetReturnsRegisteredProvider(): void
    {
        $etecsa = $this->createMock(CommunicationProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);

        $registry = new ProviderRegistry([$etecsa]);

        $this->assertSame($etecsa, $registry->get(CommunicationProviderEnum::ETECSA));
        $this->assertTrue($registry->has(CommunicationProviderEnum::ETECSA));
    }

    public function testGetThrowsWhenProviderNotRegistered(): void
    {
        $registry = new ProviderRegistry([]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('El proveedor ETECSA no está registrado');

        $registry->get(CommunicationProviderEnum::ETECSA);
    }

    public function testHasReturnsFalseWhenNotRegistered(): void
    {
        $registry = new ProviderRegistry([]);

        $this->assertFalse($registry->has(CommunicationProviderEnum::DTONE));
    }

    public function testGetForReturnsProviderWhenCapabilityIsSupported(): void
    {
        $etecsa = $this->createMock(RechargeProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);

        $registry = new ProviderRegistry([$etecsa]);

        $this->assertSame($etecsa, $registry->getFor(CommunicationProviderEnum::ETECSA, RechargeProviderInterface::class));
    }

    public function testGetForThrowsWhenCapabilityUnsupported(): void
    {
        // Un mock genérico de CommunicationProviderInterface no implementa TouristSimProviderInterface.
        $etecsa = $this->createMock(CommunicationProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);

        $registry = new ProviderRegistry([$etecsa]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no soporta esta operación');

        $registry->getFor(CommunicationProviderEnum::ETECSA, TouristSimProviderInterface::class);
    }

    public function testRegisteredListsAllProviderCodes(): void
    {
        $etecsa = $this->createMock(CommunicationProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $dtone = $this->createMock(CommunicationProviderInterface::class);
        $dtone->method('getCode')->willReturn(CommunicationProviderEnum::DTONE);

        $registry = new ProviderRegistry([$etecsa, $dtone]);

        $this->assertEqualsCanonicalizing(
            [CommunicationProviderEnum::ETECSA, CommunicationProviderEnum::DTONE],
            $registry->registered()
        );
    }
}
