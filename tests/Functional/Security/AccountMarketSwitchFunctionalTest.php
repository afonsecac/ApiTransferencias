<?php

namespace App\Tests\Functional\Security;

use App\Entity\Account;
use App\Service\AccountSecurityService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;

/**
 * COMR-64 (D): el administrador abre o cierra el mercado de una cuenta, y queda registrado quién lo cambió y
 * cuándo. Contra Postgres real.
 *
 * @covers \App\Service\AccountSecurityService
 */
class AccountMarketSwitchFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount($this->createClient(), $this->createEnvironment());
    }

    private function service(): AccountSecurityService
    {
        return self::getContainer()->get(AccountSecurityService::class);
    }

    private function reload(): Account
    {
        $id = $this->account->getId();
        $this->em->clear();

        return $this->em->getRepository(Account::class)->find($id);
    }

    public function testNewAccountsHaveNoMarketAuditTrail(): void
    {
        $account = $this->reload();

        $this->assertFalse($account->isMarketEnabled());
        $this->assertNull($account->getMarketChangedAt());
        $this->assertNull($account->getMarketChangedBy());
    }

    public function testEnablingRecordsWhoAndWhenAndPersists(): void
    {
        $admin = $this->createAdminUser();

        $this->service()->setMarketEnabled($this->account, true, $admin);

        $account = $this->reload();
        $this->assertTrue($account->isMarketEnabled());
        $this->assertSame($admin->getId(), $account->getMarketChangedBy()?->getId());
        $this->assertEqualsWithDelta(time(), $account->getMarketChangedAt()?->getTimestamp(), 5);
    }

    public function testDisablingAgainUpdatesTheAuditToTheLatestChange(): void
    {
        $first = $this->createAdminUser();
        $second = $this->createAdminUser();
        $this->service()->setMarketEnabled($this->account, true, $first);

        $reloaded = $this->reload();
        // Tras clear() el usuario queda desligado; en producción el del token siempre está gestionado.
        $this->service()->setMarketEnabled($reloaded, false, $this->em->find(\App\Entity\User::class, $second->getId()));

        $account = $this->reload();
        $this->assertFalse($account->isMarketEnabled());
        $this->assertSame($second->getId(), $account->getMarketChangedBy()?->getId());
    }

    public function testSettingTheSameValueIsANoOpAndKeepsTheOriginalAudit(): void
    {
        $first = $this->createAdminUser();
        $other = $this->createAdminUser();
        $this->service()->setMarketEnabled($this->account, true, $first);
        $changedAt = $this->reload()->getMarketChangedAt();

        $this->service()->setMarketEnabled($this->reload(), true, $this->em->find(\App\Entity\User::class, $other->getId()));

        $account = $this->reload();
        $this->assertSame($first->getId(), $account->getMarketChangedBy()?->getId(), 'No se reescribe la auditoría si no cambió nada.');
        $this->assertEquals($changedAt, $account->getMarketChangedAt());
    }

    public function testTheMarketIsOpenOnlyWithTheAccountFlagAndTheGlobalSwitch(): void
    {
        $this->service()->setMarketEnabled($this->account, true, $this->createAdminUser());

        $this->setSysConfig('market.enabled', '0');
        $this->assertFalse($this->service()->isMarketOpen($this->reload()));

        $this->setSysConfig('market.enabled', '1');
        $this->assertTrue($this->service()->isMarketOpen($this->reload()));
    }
}
