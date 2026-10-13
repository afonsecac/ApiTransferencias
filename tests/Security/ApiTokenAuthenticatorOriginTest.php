<?php

namespace App\Tests\Security;

use App\Entity\Account;
use App\Entity\Client;
use App\Repository\AccountRepository;
use App\Repository\SysConfigRepository;
use App\Security\ApiTokenAuthenticator;
use App\Security\MarketAccess;
use App\Service\IpMatcherService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Uid\Uuid;

/**
 * COMR-184: la lista de IP permitidas de una cuenta (`origin`) no se puede saltar con cabeceras que controla quien
 * llama. Antes, enviar `Referer` con el mismo host que `Host` ("petición desde una página web") dejaba pasar
 * cualquier IP con solo conocer el token.
 *
 * @covers \App\Security\ApiTokenAuthenticator
 */
class ApiTokenAuthenticatorOriginTest extends TestCase
{
    private const TOKEN = '0f9d0d0e-5c8e-4b5e-9f3a-2b1c3d4e5f60';

    private AccountRepository&MockObject $accounts;
    private ApiTokenAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepository::class);
        // MarketAccess es final: se usa el real, con el interruptor global apagado (no interviene en estas pruebas).
        $sysConfig = $this->createMock(SysConfigRepository::class);
        $sysConfig->method('findCachedValue')->willReturn('0');

        $this->authenticator = new ApiTokenAuthenticator(new NullLogger(), $this->accounts, new IpMatcherService(), new MarketAccess($sysConfig));
    }

    private function accountWithOrigin(string $origin): void
    {
        $client = $this->createMock(Client::class);
        $client->method('isIsActive')->willReturn(true);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getOrigin')->willReturn($origin);
        $account->method('getUserIdentifier')->willReturn(self::TOKEN);
        $this->accounts->method('findOneBy')->willReturn($account);
    }

    private function request(string $ip, array $headers = []): Request
    {
        $request = Request::create('/api/communication/sale', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
        $request->headers->set('X-AUTH-TOKEN', base64_encode(self::TOKEN));
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    public function testAnIpInsideTheAllowedListIsAccepted(): void
    {
        $this->accountWithOrigin('203.0.113.0/24');

        $passport = $this->authenticator->authenticate($this->request('203.0.113.9'));

        $this->assertSame(self::TOKEN, $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)->getUserIdentifier());
    }

    public function testAnIpOutsideTheAllowedListIsRejected(): void
    {
        $this->accountWithOrigin('203.0.113.0/24');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Access not allowed from this IP address');

        $this->authenticator->authenticate($this->request('198.51.100.7'));
    }

    public function testAForgedRefererMatchingTheHostDoesNotBypassTheIpList(): void
    {
        $this->accountWithOrigin('203.0.113.0/24');
        $request = $this->request('198.51.100.7', ['Host' => 'api.example.test', 'Referer' => 'https://api.example.test/dashboard']);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Access not allowed from this IP address');

        $this->authenticator->authenticate($request);
    }

    public function testAWildcardOriginAcceptsAnyIp(): void
    {
        $this->accountWithOrigin('*');

        $passport = $this->authenticator->authenticate($this->request('198.51.100.7'));

        $this->assertNotNull($passport);
    }

    public function testLoopbackIsAlwaysAccepted(): void
    {
        $this->accountWithOrigin('203.0.113.0/24');

        $passport = $this->authenticator->authenticate($this->request('127.0.0.1'));

        $this->assertNotNull($passport);
    }
}
