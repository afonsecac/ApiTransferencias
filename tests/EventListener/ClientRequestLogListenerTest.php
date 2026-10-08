<?php

namespace App\Tests\EventListener;

use App\Entity\Account;
use App\EventListener\ClientRequestLogListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @covers \App\EventListener\ClientRequestLogListener
 */
class ClientRequestLogListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private Security $security;
    private ClientRequestLogListener $listener;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->listener = new ClientRequestLogListener($this->logger, $this->security);
    }

    /** @return iterable<string, array{string}> */
    public static function loggedEndpoints(): iterable
    {
        yield 'recarga' => ['/api/communication/sale/recharge'];
        yield 'reserva' => ['/api/communication/sale/recharge/reserve'];
        yield 'paquete' => ['/api/communication/sale/package'];
    }

    /** @dataProvider loggedEndpoints */
    public function testPostToSaleEndpointWritesLog(string $path): void
    {
        $this->security->method('getUser')->willReturn(new Account());
        $body = '{"phoneNumber":"+5355555555","clientTransactionId":"abc-1"}';

        $this->logger->expects($this->once())->method('info')->with(
            'Client request',
            $this->callback(fn (array $ctx): bool => $ctx['endpoint'] === $path
                && $ctx['method'] === 'POST'
                && $ctx['payload'] === $body
                && array_key_exists('account', $ctx)
                && array_key_exists('ip', $ctx))
        );

        ($this->listener)($this->event(Request::create($path, 'POST', [], [], [], [], $body)));
    }

    public function testGetRequestIsNotLogged(): void
    {
        $this->logger->expects($this->never())->method('info');

        ($this->listener)($this->event(Request::create('/api/communication/sale/recharge', 'GET')));
    }

    public function testOtherEndpointIsNotLogged(): void
    {
        $this->logger->expects($this->never())->method('info');

        ($this->listener)($this->event(Request::create('/api/communication/sale/123/other', 'POST', [], [], [], [], '{}')));
    }

    public function testSubRequestIsNotLogged(): void
    {
        $this->logger->expects($this->never())->method('info');

        ($this->listener)($this->event(Request::create('/api/communication/sale/recharge', 'POST', [], [], [], [], '{}'), false));
    }

    public function testLoggerFailureNeverBreaksTheRequest(): void
    {
        $this->logger->method('info')->willThrowException(new \RuntimeException('disk full'));

        ($this->listener)($this->event(Request::create('/api/communication/sale/recharge', 'POST', [], [], [], [], '{}')));

        $this->addToAssertionCount(1);
    }

    private function event(Request $request, bool $main = true): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
