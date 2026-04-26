<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health')]
class HealthCheckController extends AbstractController
{
    #[Route('/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');

            return new JsonResponse(['status' => 'ok', 'database' => 'connected']);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['status' => 'error', 'database' => 'disconnected'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
