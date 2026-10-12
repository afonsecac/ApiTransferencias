<?php

namespace App\Tests\Entity;

use App\Entity\Account;
use PHPUnit\Framework\TestCase;

/**
 * COMR-64 (D): la cuenta habilitada para mercado recibe ROLE_MARKET_API_USER además de sus roles de siempre;
 * las demás no cambian de roles.
 *
 * @covers \App\Entity\Account
 */
class AccountMarketRoleTest extends TestCase
{
    public function testMarketIsDisabledByDefault(): void
    {
        $this->assertFalse((new Account())->isMarketEnabled());
    }

    public function testCurrentAccountsKeepExactlyTheirRoles(): void
    {
        $account = (new Account())->setRoles(['ROLE_COM_API_USER']);

        $this->assertEqualsCanonicalizing(['ROLE_COM_API_USER', 'ROLE_API_USER'], $account->getRoles());
    }

    public function testTheFlagAloneDoesNotGrantTheRole(): void
    {
        // El rol lo concede el autenticador cuando además está encendido el interruptor global.
        $account = (new Account())->setMarketEnabled(true);

        $this->assertNotContains('ROLE_MARKET_API_USER', $account->getRoles());
    }

    public function testGrantingTheMarketRoleAddsItWithoutRemovingTheOthers(): void
    {
        $account = (new Account())->setRoles(['ROLE_COM_API_USER'])->setMarketEnabled(true);
        $account->grantMarketRole();

        $this->assertEqualsCanonicalizing(['ROLE_COM_API_USER', 'ROLE_API_USER', 'ROLE_MARKET_API_USER'], $account->getRoles());
    }

    public function testTheGrantIsNotPersistedAsAStoredRole(): void
    {
        $account = (new Account())->setRoles(['ROLE_COM_API_USER']);
        $account->grantMarketRole();

        $reflection = new \ReflectionProperty($account, 'roles');
        $this->assertSame(['ROLE_COM_API_USER'], $reflection->getValue($account));
    }
}
