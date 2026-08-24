<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CommunicationPackage;
use App\State\CommunicationClientPackageProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\State\CommunicationClientPackageProvider
 */
class CommunicationClientPackageProviderTest extends TestCase
{
    private ProviderInterface&MockObject $catalogProvider;
    private CommunicationClientPackageProvider $provider;

    protected function setUp(): void
    {
        $this->catalogProvider = $this->createMock(ProviderInterface::class);
        $this->provider = new CommunicationClientPackageProvider($this->catalogProvider);
    }

    public function testDelegatesAlwaysInTheCatalogProvider(): void
    {
        $operation = $this->createMock(Operation::class);
        $uriVariables = ['foo' => 'bar'];
        $context = ['request' => null];

        $v2Packages = [$this->createMock(CommunicationPackage::class)];
        $this->catalogProvider->expects($this->once())->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($v2Packages);

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertSame($v2Packages, $result);
    }
}
