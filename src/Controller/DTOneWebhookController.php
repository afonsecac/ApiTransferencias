<?php

namespace App\Controller;

use App\DTO\DTOneWebhookPayloadDto;
use App\Service\DTOne\DTOneWebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Callback público de DTOne. Fuera de los firewalls `dashboard`/`main` (ver
 * security.yaml, access_control '^/api/webhooks/dtone' => PUBLIC_ACCESS):
 * DTOne no puede enviar nuestro Bearer/API-Key, así que la única defensa es
 * el token no adivinable en la propia ruta.
 *
 * Un token inválido responde 404 genérico — nunca 401/403, para no confirmar
 * a un tercero que la ruta existe con otro formato de token. El body nunca
 * se usa como fuente de verdad (ver DTOneWebhookService).
 */
class DTOneWebhookController extends AbstractController
{
    public function __construct(
        private readonly DTOneWebhookService $webhookService,
    ) {
    }

    #[Route('/api/webhooks/dtone/{token}', name: 'dtone_webhook', methods: ['POST'])]
    public function __invoke(string $token, DTOneWebhookPayloadDto $payload): JsonResponse
    {
        if (!$this->webhookService->isValidToken($token)) {
            throw $this->createNotFoundException();
        }

        $this->webhookService->handle($payload);

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }
}
