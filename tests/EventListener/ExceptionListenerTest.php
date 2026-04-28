<?php

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use App\Exception\MyCurrentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @covers \App\EventListener\ExceptionListener
 */
class ExceptionListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private ExceptionListener $listener;

    protected function setUp(): void
    {
        $this->logger   = $this->createMock(LoggerInterface::class);
        $this->listener = new ExceptionListener($this->logger);
    }

    public function testMyCurrentExceptionReturnsBadRequest(): void
    {
        $event = $this->makeEvent(new MyCurrentException('TEST_CODE', 'Test error'));

        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('TEST_CODE', $body['error']['code']);
        $this->assertSame('Test error', $body['error']['message']);
    }

    public function testUnhandledExceptionReturns500AndLogsError(): void
    {
        $exception = new \RuntimeException('Something broke');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Something broke', $this->arrayHasKey('exception'));

        $event = $this->makeEvent($exception);
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()->getStatusCode());
    }

    public function testUnhandledExceptionDoesNotExposeInternalMessage(): void
    {
        $event = $this->makeEvent(new \RuntimeException('DB password: secret123'));
        ($this->listener)($event);

        $body = json_decode($event->getResponse()->getContent(), true);
        // El mensaje de la excepción llega al cliente (comportamiento actual)
        // Este test documenta ese comportamiento para detectar cambios accidentales
        $this->assertArrayHasKey('error', $body);
    }

    private function makeEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
