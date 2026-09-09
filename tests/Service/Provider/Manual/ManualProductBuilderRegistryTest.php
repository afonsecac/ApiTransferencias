<?php

namespace App\Tests\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Service\Provider\Manual\CsqManualProductBuilder;
use App\Service\Provider\Manual\EtecsaManualProductBuilder;
use App\Service\Provider\Manual\ManualProductBuilderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\Manual\ManualProductBuilderRegistry
 */
class ManualProductBuilderRegistryTest extends TestCase
{
    private ManualProductBuilderRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ManualProductBuilderRegistry([
            new CsqManualProductBuilder(),
            new EtecsaManualProductBuilder(),
        ]);
    }

    public function testHasReturnsTrueForRegisteredProvider(): void
    {
        $this->assertTrue($this->registry->has(CommunicationProviderEnum::CSQ));
        $this->assertTrue($this->registry->has(CommunicationProviderEnum::ETECSA));
    }

    public function testHasReturnsFalseForProviderWithoutBuilder(): void
    {
        $this->assertFalse($this->registry->has(CommunicationProviderEnum::DTONE));
    }

    public function testGetReturnsTheMatchingBuilder(): void
    {
        $this->assertInstanceOf(CsqManualProductBuilder::class, $this->registry->get(CommunicationProviderEnum::CSQ));
        $this->assertInstanceOf(EtecsaManualProductBuilder::class, $this->registry->get(CommunicationProviderEnum::ETECSA));
    }

    public function testGetThrowsForProviderWithoutBuilder(): void
    {
        $this->expectException(MyCurrentException::class);

        $this->registry->get(CommunicationProviderEnum::DTONE);
    }

    public function testSupportedListsOnlyRegisteredCodes(): void
    {
        $codes = $this->registry->supported();

        $this->assertContains(CommunicationProviderEnum::CSQ, $codes);
        $this->assertContains(CommunicationProviderEnum::ETECSA, $codes);
        $this->assertNotContains(CommunicationProviderEnum::DTONE, $codes);
        $this->assertCount(2, $codes);
    }
}
