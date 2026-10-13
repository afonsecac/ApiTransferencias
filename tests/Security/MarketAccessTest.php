<?php

namespace App\Tests\Security;

use App\Entity\Account;
use App\Repository\SysConfigRepository;
use App\Security\MarketAccess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * COMR-64 (D, ADR-001): el mercado se abre cuenta a cuenta (Account::marketEnabled) y solo si además está
 * encendido el interruptor global `market.enabled`. Ambos apagados por defecto.
 *
 * @covers \App\Security\MarketAccess
 */
class MarketAccessTest extends TestCase
{
    private SysConfigRepository&MockObject $sysConfig;
    private MarketAccess $access;

    protected function setUp(): void
    {
        $this->sysConfig = $this->createMock(SysConfigRepository::class);
        $this->access = new MarketAccess($this->sysConfig);
    }

    private function globalSwitch(?string $value): void
    {
        $this->sysConfig->method('findCachedValue')->with('market.enabled')->willReturn($value);
    }

    private function account(bool $marketEnabled): Account
    {
        return (new Account())->setMarketEnabled($marketEnabled);
    }

    public function testTheGlobalSwitchIsOffWhenTheKeyDoesNotExist(): void
    {
        $this->globalSwitch(null);

        $this->assertFalse($this->access->isGloballyEnabled());
    }

    /**
     * @dataProvider offValues
     */
    public function testTheGlobalSwitchIsOffForAnythingButOne(?string $value): void
    {
        $this->globalSwitch($value);

        $this->assertFalse($this->access->isGloballyEnabled());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function offValues(): iterable
    {
        yield 'cero' => ['0'];
        yield 'vacío' => [''];
        yield 'texto' => ['yes'];
        yield 'verdadero' => ['true'];
    }

    public function testTheGlobalSwitchIsOnOnlyWithTheValueOne(): void
    {
        $this->globalSwitch('1');

        $this->assertTrue($this->access->isGloballyEnabled());
    }

    public function testAnAccountWithoutTheFlagIsNeverOpenEvenWithTheGlobalSwitchOn(): void
    {
        $this->globalSwitch('1');

        $this->assertFalse($this->access->isOpenFor($this->account(false)));
    }

    public function testAnAccountWithTheFlagIsNotOpenWhileTheGlobalSwitchIsOff(): void
    {
        $this->globalSwitch('0');

        $this->assertFalse($this->access->isOpenFor($this->account(true)));
    }

    public function testAnAccountIsOpenOnlyWithTheFlagAndTheGlobalSwitchBothOn(): void
    {
        $this->globalSwitch('1');

        $this->assertTrue($this->access->isOpenFor($this->account(true)));
    }
}
