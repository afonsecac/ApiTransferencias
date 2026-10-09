<?php

namespace App\Controller;

use App\Exception\MyCurrentException;
use App\Service\RechargeVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verificación pública de una recarga — destino del QR impreso en el
 * comprobante que recibe el cliente final. Sin autenticación (lo consulta
 * cualquiera que escanee el QR, ver security.yaml access_control), así que
 * solo responde con datos si la venta existe Y está Completed; cualquier
 * otro caso (no existe, o existe pero sigue Pending/Failed/etc.) responde
 * el mismo 404 genérico, para no confirmar a un tercero el estado de una
 * recarga ajena.
 */
#[Route('/api/verify')]
class RechargeVerificationController extends AbstractController
{
    public function __construct(
        private readonly RechargeVerificationService $verificationService,
    ) {
    }

    #[Route('/{transactionId}', name: 'api_recharge_verify', methods: ['GET'])]
    public function verify(string $transactionId): JsonResponse
    {
        try {
            $result = $this->verificationService->verify($transactionId);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($result);
    }
}
