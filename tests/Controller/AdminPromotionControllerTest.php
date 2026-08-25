<?php

namespace App\Tests\Controller;

use App\Controller\AdminPromotionController;
use App\DTO\CreateAdminPromotionDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\AdminPromotionController
 *
 * Fase 2 de la deprecación de V1: el alta de promociones V1 queda cerrada
 * en este endpoint — usar POST /promotions/v2 en su lugar.
 */
class AdminPromotionControllerTest extends TestCase
{
    public function testIndexAlwaysRejectsWithGone(): void
    {
        $controller = new AdminPromotionController();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $response = $controller->index(new CreateAdminPromotionDto());

        $this->assertSame(Response::HTTP_GONE, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('V1_PROMOTION_CREATION_DISABLED', $body['error']['code']);
    }
}
