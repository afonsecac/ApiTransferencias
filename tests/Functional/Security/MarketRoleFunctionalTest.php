<?php

namespace App\Tests\Functional\Security;

use App\Entity\Account;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-64 (D): de punta a punta por el firewall de /api, con X-AUTH-TOKEN. La cuenta recibe
 * ROLE_MARKET_API_USER solo con su flag y el interruptor global encendidos; las cuentas actuales no cambian.
 *
 * @coversNothing
 */
class MarketRoleFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount($this->createClient(), $this->createEnvironment());
        $this->account->setRoles(['ROLE_COM_API_USER']);
        $this->em->flush();
    }

    /**
     * @return list<string>
     */
    private function rolesSeenByTheFirewall(): array
    {
        $this->em->clear();
        $request = Request::create('/api/_ejemplo/roles', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AUTH_TOKEN' => base64_encode((string) $this->account->getAccessToken()),
        ]);

        $response = self::$kernel->handle($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true)['roles'];
    }

    private function marketGlobalSwitch(string $value): void
    {
        $this->setSysConfig('market.enabled', $value);
    }

    public function testACurrentAccountKeepsExactlyItsRolesWhateverTheGlobalSwitch(): void
    {
        $this->marketGlobalSwitch('1');

        $this->assertSame(['ROLE_API_USER', 'ROLE_COM_API_USER'], $this->rolesSeenByTheFirewall());
    }

    public function testAnAccountWithTheFlagButTheGlobalSwitchOffDoesNotGetTheRole(): void
    {
        $this->account->setMarketEnabled(true);
        $this->em->flush();
        $this->marketGlobalSwitch('0');

        $this->assertNotContains('ROLE_MARKET_API_USER', $this->rolesSeenByTheFirewall());
    }

    public function testAnAccountWithoutTheFlagDoesNotGetTheRoleEvenWithTheGlobalSwitchOn(): void
    {
        $this->marketGlobalSwitch('1');

        $this->assertNotContains('ROLE_MARKET_API_USER', $this->rolesSeenByTheFirewall());
    }

    public function testAnAccountWithTheFlagAndTheGlobalSwitchOnGetsTheRoleAndKeepsTheRest(): void
    {
        $this->account->setMarketEnabled(true);
        $this->em->flush();
        $this->marketGlobalSwitch('1');

        $this->assertSame(['ROLE_API_USER', 'ROLE_COM_API_USER', 'ROLE_MARKET_API_USER'], $this->rolesSeenByTheFirewall());
    }
}
