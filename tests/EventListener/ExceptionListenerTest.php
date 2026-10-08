<?php

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use App\Exception\MyCurrentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException as SerializerUnexpectedValueException;
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
        // El mensaje interno NO debe llegar al cliente: se devuelve un mensaje
        // genérico y el detalle queda solo en logs.
        $this->assertArrayHasKey('error', $body);
        $this->assertStringNotContainsString('secret123', $body['error']['message']);
        $this->assertSame('Internal server error', $body['error']['message']);
    }

    public function testMalformedJsonBodyReturns400WithInvalidJsonBodyCode(): void
    {
        $this->logger->expects($this->never())->method('error');

        $event = $this->makeEvent(new NotEncodableValueException('Syntax error'), $this->post());
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Invalid JSON body', $body['error']['message']);
        $this->assertSame('INVALID_JSON_BODY', $body['error']['code']);
    }

    public function testWrongTypeInBodyReturns400WithFieldDetailAndNoClassNames(): void
    {
        $this->logger->expects($this->never())->method('error');
        $exception = NotNormalizableValueException::createForUnexpectedDataType(
            'The type of the "packageId" attribute for class "App\\Entity\\CommunicationSaleRecharge" must be one of "int" ("string" given).',
            'abc',
            ['int'],
            'packageId',
        );

        $event = $this->makeEvent($exception, $this->post());
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Invalid request body', $body['error']['message']);
        $this->assertSame(['packageId: expected int, got string'], $body['error']['details']);
        $this->assertStringNotContainsString('App\\Entity', $event->getResponse()->getContent());
    }

    public function testPartialDenormalizationReturns400WithOneDetailPerField(): void
    {
        $errors = [
            NotNormalizableValueException::createForUnexpectedDataType('x', 'abc', ['int'], 'packageId'),
            NotNormalizableValueException::createForUnexpectedDataType('y', 53555555, ['string'], 'phoneNumber'),
        ];

        $event = $this->makeEvent(new PartialDenormalizationException([], $errors), $this->post());
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame(
            ['packageId: expected int, got string', 'phoneNumber: expected string, got int'],
            $body['error']['details'],
        );
    }

    public function testMissingConstructorArgumentsReturns400ListingTheFields(): void
    {
        $exception = new MissingConstructorArgumentsException('Cannot create an instance of "App\\DTO\\ReserveRecharge"', 0, null, ['packageId', 'phoneNumber']);

        $event = $this->makeEvent($exception, $this->post());
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Invalid request body', $body['error']['message']);
        $this->assertSame(['packageId: this field is required', 'phoneNumber: this field is required'], $body['error']['details']);
        $this->assertStringNotContainsString('App\\DTO', $event->getResponse()->getContent());
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function genericBodyErrors(): iterable
    {
        yield 'datos mal formados' => [new SerializerUnexpectedValueException('The input data is misformatted.')];
        yield 'atributos extra' => [new ExtraAttributesException(['zzz'])];
    }

    /** @dataProvider genericBodyErrors */
    public function testOtherSerializerBodyErrorsReturn400(\Throwable $exception): void
    {
        $this->logger->expects($this->never())->method('error');

        $event = $this->makeEvent($exception, $this->post());
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Invalid request body', $body['error']['message']);
    }

    public function testSerializerErrorOnSafeRequestStaysAServerError(): void
    {
        // En un GET es un fallo al normalizar la respuesta (nuestro), no un error del cliente.
        $this->logger->expects($this->once())->method('error');

        $event = $this->makeEvent(new SerializerUnexpectedValueException('The input data is misformatted.'), Request::create('/', 'GET'));
        ($this->listener)($event);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()->getStatusCode());
    }

    private function post(): Request
    {
        return Request::create('/api/communication/sale/recharge', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
    }

    private function makeEvent(\Throwable $exception, ?Request $request = null): ExceptionEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request ??= Request::create('/');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
