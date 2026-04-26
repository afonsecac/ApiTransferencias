<?php

namespace App\Tests\Controller;

use App\Controller\HealthCheckController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\HealthCheckController
 */
class HealthCheckControllerTest extends TestCase
{
    private HealthCheckController $controller;

    protected function setUp(): void
    {
        $this->controller = new HealthCheckController();
    }

    public function testLiveReturnsOk(): void
    {
        $response = $this->controller->live();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(['status' => 'ok'], $data);
    }

    public function testReadyReturnsOkWhenDatabaseConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $response = $this->controller->ready($connection);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('connected', $data['database']);
    }

    public function testReadyReturnsErrorWhenDatabaseDisconnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $response = $this->controller->ready($connection);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('disconnected', $data['database']);
    }
}
